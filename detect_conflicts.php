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

// GLOBAL SETTINGS
$settings = [
	'repo' => 'git@github.com:sortex/detect.git',
	'var'  => '.cache/repo-cms',
	'hipchat' => [
		'token' => '08b766d5c5e8d2340f3910aa4f770d',
		'from'  => 'Bob',
	]
];

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
$git = new Git($settings['var']);

// Make sure to chop down 'refs/heads/'
$subject_branch = $payload->ref;
$branch_parts = explode('/', $subject_branch);
array_shift($branch_parts);
array_shift($branch_parts);
$subject_branch = implode('/', $branch_parts);

// SETUP
if ( ! is_dir($settings['var']))
{
	file_put_contents('.logs/git.log', 'Cmd: git clone '.$settings['repo'].' '.$settings['var']."\n", FILE_APPEND);
	$git->execute('clone '.escapeshellcmd($settings['repo']).' '.escapeshellcmd($settings['var']));
} else {
	file_put_contents('.logs/git.log', "Cmd: git fetch --prune\n", FILE_APPEND);
	$git->execute('fetch --prune');
}

// Detect remote branches
$branches = $git->execute('for-each-ref refs/remotes/ --format=\'%(refname:short)\'');
$branches = explode("\n", $branches);
file_put_contents('.logs/git.log', "\nBranches:".implode(', ', $branches)."\n", FILE_APPEND);

$failures = [];
foreach ($branches as $branch)
{
	// Pull out remote name from branch ref
	$branch_parts = explode('/', $branch);
	$remote_name  = array_shift($branch_parts);
	$branch       = implode('/', $branch_parts);
	

	if (empty($branch) || $branch === 'HEAD') continue;
	
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
		file_put_contents('.logs/git.log', 'Cmd: git pull --ff-only origin '.escapeshellcmd($subject_branch)."\n", FILE_APPEND);
		$status = $git->execute('pull --ff-only origin '.escapeshellcmd($subject_branch));
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

	$ops = array_unique($ops);
	$msg = '<strong>'.implode(', ', $ops).'</strong> - Your latest commit `<strong>'.implode(', ', $commit_msgs).'</strong>` is conflicting with the following branches: <strong>'.implode(', ', $failures).'</strong>';

	$chat = new Hipchat($settings['hipchat']['token']);
	$chat->message_room(
		'CEOs',
		$settings['hipchat']['from'], 
		$msg, 
		TRUE, 
		Hipchat::COLOR_RED
	);
}


// The end.


