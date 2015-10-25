<?php

// Simple SPK Repo
// Syno Repo

require "includes/Tar.php";

set_time_limit(200);
error_reporting(E_ALL);
ini_set("display_errors", "On");


function get_data($url, $github_token) {
	$ch = curl_init();
	$timeout = 5;

	if(!empty($github_token)){
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
	    "Authorization: token $github_token"
	    ));
	}
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 1);
	// apparently github requires a user agent and some server configuration do not include one.
	curl_setopt($ch, CURLOPT_USERAGENT, "synorepo");

	$data = curl_exec($ch);
	curl_close($ch);
	return $data;
}

function download_file($url, $path, $github_token){
	$realurl = find_real_url($url, array(
		CURLOPT_HTTPHEADER => (!empty($github_token)) ? array(
	    "Authorization: token $github_token"
	    ) : "",
	    CURLOPT_USERAGENT => "synorepo",
	    CURLOPT_SSL_VERIFYHOST => 1
		));

	$file2 = fopen($path, 'w+');
	$ch2=curl_init($realurl);
	if(!empty($github_token)){
		curl_setopt($ch2, CURLOPT_HTTPHEADER, array(
	    "Authorization: token $github_token"
	    ));
	}
	curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch2, CURLOPT_HEADER, 0);
	curl_setopt($ch2, CURLOPT_FILE, $file2); //auto write to file

	curl_setopt($ch2, CURLOPT_CONNECTTIMEOUT, 5);
	curl_setopt($ch2, CURLOPT_TIMEOUT, 5040);
	// apparently github requires a user agent and some server configuration do not include one.
	curl_setopt($ch2, CURLOPT_USERAGENT, "synorepo");
	curl_setopt($ch2, CURLOPT_SSL_VERIFYHOST, 1);

	$result = true;
	if(curl_exec($ch2) === false)
	{
	    echo 'Curl error: ' . curl_error($ch2);
	    $result = false;
	}

	curl_close($ch2);
	fclose($file2);

	if(!$result){
		@unlink($path);
	}

	return $result;
}

function find_real_url($url, $params = array()){
	$mr = 5;
	$newurl = $url;
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false); 
    
	foreach ($params as $key => $value) {
		curl_setopt($ch, $key, $value);
	}

    curl_setopt($ch, CURLOPT_HEADER, true); 
    curl_setopt($ch, CURLOPT_NOBODY, true); 
    curl_setopt($ch, CURLOPT_FORBID_REUSE, false); 
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
    do { 
        curl_setopt($ch, CURLOPT_URL, $newurl); 
        $header = curl_exec($ch); 
        if (curl_errno($ch)) { 
            $code = 0; 
        } else { 
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); 
            if ($code == 301 || $code == 302) { 
                preg_match('/Location:(.*?)\n/', $header, $matches); 
                $newurl = trim(array_pop($matches)); 
            } else { 
                $code = 0; 
            } 
        } 
    } while ($code && --$mr); 
    curl_close($ch); 
    if (!$mr) { 
    	trigger_error('Too many redirects.', E_USER_WARNING); 
        return false; 
    } 
    return $newurl;
    
}


function packSPK($dir, $destPackage){
    $oldDir = getcwd();

    chdir($dir);

	// echo "create $dir to $destPackage";
	$package=new Archive_Tar("package.tgz","gz");
	$package->setErrorHandling(PEAR_ERROR_PRINT);
	$package->create("package"); 

	$destPackage = new Archive_Tar($destPackage);
	$destPackage->create("INFO scripts package.tgz");

	@unlink("package.tgz");

    chdir($oldDir);
}

function extractSource($tarfile, $target_dir, $modes){
	$tar = new Archive_Tar($tarfile);

	$root_folders = array();
	foreach ($tar->listContent() as  $value) {
		if(substr($value["filename"], -1) == "/" && substr_count($value["filename"], "/") == 1){
			$root_folders[] = $value["filename"];
		}
	}
	if(count($root_folders) > 1){
		echo "Error: too much root folders found.";
	}
	$root_folder = $root_folders[0];
	$extract_list = array();
	foreach ($tar->listContent() as $value) {
		if(substr($value["filename"], 0, strlen($root_folder)) == $root_folder 
			&& strlen($value["filename"]) > strlen($root_folder)){
			$extract_list[] = $value["filename"];
		}
	}

	$tar->extractList($extract_list, $target_dir, $root_folder, true);

	chmod_r($target_dir, $modes);
}

function getSPKInfo($name, $info, $branch){
	unset($info['id']);
    unset($info['arch']);
    $info['beta'] = ($branch != "master") ? true : false;
    $info['link'] = "http://syno.parrot-bytes.com/spks/$name/$branch/$name-".$info['version'].".spk";
    $info['desc'] = (isset($info['desc_local']) ? $info['desc_local'] : $info['description']);
    if(isset($info['package_icon'])){
        $info['icon'] = $info['package_icon'];
        unset($info['package_icon']);
    }
    unset($info['desc_local']);
    unset($info['description']);

    return $info;
}

function getGithubPath($path, $placeholder){
	if(!is_array($placeholder)){
		$placeholder = array($placeholder);
	}
	return preg_replace(
			array_fill(0, count($placeholder), '/\{[^\}]*\}/'), 
			array_map("preg_quote", $placeholder), 
			$path,
			1);
}

function fetchJson($path, $github_token){
	return json_decode(get_data($path, $github_token), true);
}

function chmod_r($path, $modes) {
    $dir = new DirectoryIterator($path);
    foreach ($dir as $item) {
        if ($item->isDir() && !$item->isDot()) {
        	@chmod($item->getPathname(), $modes["dir"]);
            chmod_r($item->getPathname(), $modes);
        } else {
        	@chmod($item->getPathname(), $modes["file"]);
        }
    }
}

function syno_repo($github_repos, $github_token, $modes){
	$result = array();

	foreach ($github_repos as $repoinfo) {
		$reponame = $repoinfo["user"] . '/' . $repoinfo["repo"];
		$branch = $repoinfo["branch"];
		$repo = fetchJson("https://api.github.com/repos/$reponame", $github_token);
		if(empty($repo["contents_url"])){
			continue;
		}

		$info_github = fetchJson(getGithubPath($repo["contents_url"] . "?ref=$branch", "INFO"), $github_token);
		$info = parse_ini_string(get_data($info_github["download_url"], $github_token));
		$package = preg_replace("/[^a-z0-9\.\-\_]/", '', strtolower($info["package"]));
		$package_dir = "./spks/$package/$branch";

		$release_name = $package . "-" . $info['version'] . ".spk";

		if(!is_dir($package_dir)){
			mkdir($package_dir);
			chmod($package_dir, $modes["dir"]);
		}


		// check if there is no fitting spk or an older spk
	    if(!is_file("$package_dir/$release_name")){
	    	$oldSPKs = glob("$package_dir/*.spk");
	    	if($oldSPKs !== false && count($oldSPKs) > 0){

	            // remove old spks
	        	foreach($oldSPKs as $filename){
	        		@unlink($filename);
	        	}
	        }
	        // pull files from github
	        if(download_file(
	        	getGithubPath($repo["archive_url"], array("tarball", "/$branch")),
	        	"$package_dir/$package.tar",
	        	$github_token))
	        {
	        	// extract the downloaded package
	        	extractSource("$package_dir/$package.tar", "$package_dir", $modes);

	        	unlink("$package_dir/$package.tar");

	        	// create new spk
	        	packSPK($package_dir, $release_name);
	        }

	    }

	    array_push($result, getSPKInfo($package, $info, $branch));

	}

	echo json_encode($result);
}

?>