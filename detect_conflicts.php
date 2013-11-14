<?php
/**
 * Auto-conflict detector
 *
 * Runs as a single webpage that receives POSTs from GitHub
 * once a branch is pushed, and detects conflicts with all
 * other branches in repository.
 */

include 'src/Git.php';
include 'src/Hipchat.php';

file_put_contents('.logs/git.log', "REQUEST PAYLOAD: \n".$_REQUEST['payload']."\n", FILE_APPEND);

// PARSE REQUEST
try {
	$payload = json_decode($_REQUEST['payload']);
}
catch (Exception $e)
{
	// TODO: Notification?
	die('Invalid request payload');
}

// INIT
$room_id  = $_GET['room'];
$token    = $_GET['t'];
$bot_name = $_GET['from'];
$repo_url = 'git@github.com:'.$payload->repository->owner->name.'/'.$payload->repository->name.'.git';
$repo_dir = '.cache/'.$payload->repository->name;
$git      = new Git($repo_dir);
$chat     = new Hipchat($token);

// Make sure to chop down 'refs/heads/'
$subject_branch = $payload->ref;
$branch_parts = explode('/', $subject_branch);
array_shift($branch_parts);
array_shift($branch_parts);
$subject_branch = implode('/', $branch_parts);

// Do not process deletions
if ($payload->deleted) die();

// SETUP
if ( ! is_dir($repo_dir))
{
	file_put_contents('.logs/git.log', "Cmd: git clone $repo_url $repo_dir\n", FILE_APPEND);
	$git->execute("clone $repo_url ".escapeshellcmd($repo_dir));
} else {
	file_put_contents('.logs/git.log', "Cmd: git fetch --prune\n", FILE_APPEND);
	$git->execute('fetch --prune');
}

// Detect remote branches
$branches = $git->execute('for-each-ref refs/remotes/ --format=\'%(refname:short)\'');
$branches = explode("\n", $branches);
file_put_contents('.logs/git.log', "\nBranches:".implode(', ', $branches)."\n", FILE_APPEND);

// Should we ignore certain branches?
$ignore = array();
if (isset($_GET['ignore']))
{
	$ignore = explode(',', $_GET['ignore']);
}

$failures = [];
foreach ($branches as $branch)
{
	// Pull out remote name from branch ref
	$branch_parts = explode('/', $branch);
	$remote_name  = array_shift($branch_parts);
	$branch       = implode('/', $branch_parts);

	// Skip HEAD and empty strings
	if (empty($branch) || $branch === 'HEAD') continue;

	// Skip subject branch
	if ($branch == $subject_branch) continue;

	// Skip blacklisted branches
	if (in_array($branch, $ignore)) continue;
	
	file_put_contents('.logs/git.log', "\nBRANCH: $branch REMOTE: $remote_name/$branch\n", FILE_APPEND);

	try
	{
		// Clean previous local branch if exists
		file_put_contents('.logs/git.log', "Cmd: git branch -D $branch\n", FILE_APPEND);
		$git->execute("branch -D $branch");
	}
	catch (Exception $e)
	{
	}

	file_put_contents('.logs/git.log', "Cmd: git checkout -b $branch $remote_name/$branch\n", FILE_APPEND);
	$git->execute("checkout -b $branch $remote_name/$branch");

	try
	{
		file_put_contents('.logs/git.log', 'Cmd: git pull origin '.escapeshellcmd($subject_branch)."\n", FILE_APPEND);
		$status = $git->execute('pull origin '.escapeshellcmd($subject_branch));
	}
	catch (Exception $e)
	{
		$failures[] = $branch;
	}

	file_put_contents('.logs/git.log', 'reset --hard origin/develop'."\n", FILE_APPEND);
	$git->execute('reset --hard origin/develop');
}

if ($failures)
{
	// There could be multiple commits with multiple authors
	$ops = [];
	$commit_msgs = [];
	foreach ($payload->commits as $commit)
	{
		$ops[] = $commit->author->name;
		$commit_msgs[] = $commit->message;
	}
	$ops     = array_unique($ops);
	$pusher  = $payload->pusher->name;
	$commits = implode(', ', $commit_msgs);
	$commits = strlen($commits) > 30 ? substr($commits, 0, 29).'...' : $commits;

	$msg = '<strong>'.$pusher.'</strong> - Your latest pushes `<strong>'.$commits.'</strong>` is conflicting with the following branches: <strong>'.implode(', ', $failures).'</strong>';

	$chat->message_room($room_id, $bot_name, $msg, TRUE, Hipchat::COLOR_RED);
}


// The end.

