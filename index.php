<?php

require "syno-repo.php";


if(!empty($_REQUEST['branch'])){
	$branch = $_REQUEST['branch'];
} else {
	$beta_channel = 
		isset($_REQUEST['package_update_channel']) && 
		strtolower($_REQUEST['package_update_channel']) == 'beta';
	$branch = ($beta_channel) ? 'develop' : 'master';
}


// github token
$github_token = "xxx";

// github repos
$github_repos = array(
	array(
		'user' 		=> 'andreasgrill',
		'repo' 		=> 'vpnrouting',
		'branch'	=> $branch,
	)
);

// file/dir modes
$modes = array(
	'dir' => 0777, 
	'file' => 0777);

syno_repo($github_repos, $github_token, $modes);

// file_put_contents("post.txt", var_export($_REQUEST, true). var_export($github_repos, true));
// chmod("post.txt", 0777);

?>