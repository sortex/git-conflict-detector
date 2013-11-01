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

$branches = [ 'develop', 'adam', 'rafi' ];

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

// Not sure if 'refs/heads/master' if ok here or i need to explode
$subject_branch = $payload->ref;

// SETUP
if ( ! is_dir($settings['var']))
{
//	$debug = 'clone '.escapeshellcmd($settings['repo']).' '.escapeshellcmd($settings['var']);

//	file_put_contents('.logs/git.log', $debug, FILE_APPEND);

//	$git->execute('clone '.escapeshellcmd($settings['repo']).' '.escapeshellcmd($settings['var']));
}

$git->execute('fetch --prune');

$failures = [];
foreach ($branches as $branch)
{
	$branch_parts = explode('/', $branch);
	$branch = end($branch_parts);

	try
	{
		$git->execute('branch -D '.$branch);
	}
	catch (Exception $e)
	{
	}

	$git->execute('checkout -b '.$branch.' origin/'.$branch);

	try
	{
		$status = $git->execute('pull --ff-only origin '.escapeshellcmd($subject_branch));
	}
	catch (Exception $e)
	{
		file_put_contents('.logs/git.log', $e->getMessage(), FILE_APPEND);
		$failures[] = $branch;
	}

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


