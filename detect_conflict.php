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
	'repo' => 'git@github.com:sortex/cms.git',
	'var'  => '/tmp/repo-cms',
	'hipchat' => [
		'token' => '',
		'from'  => 'Bob',
	]
];

$branches = [ 'develop', 'feature/core' ];

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
	$git->execute('clone '.escapeshellcmd($settings['repo']).' '.escapeshellcmd($settings['var']));
}

foreach ($branches as $branch)
{
	$git->execute('checkout '.$branch);

	try
	{
		$status = $git->execute('pull origin --ff-only '.escapeshellcmd($subject_branch));
	}
	catch (Exception $e)
	{                                                             A
		$failures[] = $branch;
	}

	$git->execute('reset --hard origin/develop');
}

if ($failures)
{
	// There could be multiple commits with multiple authors
	$ops = [];
	foreach ($request->commits as $commit)
	{
		$ops[] = $commit->author->name;
	}
	$msg = implode(', ', $ops).' - Your lastest commit is conflicting with the following branches: '.implode(', ', $failures);

	$chat = new Hipchat($settings['hipchat']['token']);
	$chat->message(
		'CMS', $settings['hipchat']['from'], 
		$msg, 
		TRUE, 
		Hipchat::COLOR_RED
	);
}


// The end.


