<?php

function list_files($path)
{
    config_oauth();
    $refresh_token = getConfig('refresh_token');
    if (!$refresh_token) return '';

    if (!($_SERVER['access_token'] = getcache('access_token', $_SERVER['disktag']))) {
        $response = get_access_token($refresh_token);
        if (isset($response['stat'])) return message($response['body'], 'Error', $response['stat']);
    }
//return tttt($path, $_SERVER['access_token']);
    $_SERVER['ishidden'] = passhidden($path);
    if (isset($_GET['thumbnails'])) {
        if ($_SERVER['ishidden']<4) {
            if (in_array(strtolower(substr($path, strrpos($path, '.') + 1)), $exts['img'])) {
                return get_thumbnails_url($path, $_GET['location']);
            } else return output(json_encode($exts['img']),400);
        } else return output('',401);
    }

    $path = path_format($path);
    //error_log($path);
    if ($_SERVER['is_guestup_path']&&!$_SERVER['admin']) {
        $files = json_decode('{"folder":{}}', true);
    } elseif (!getConfig('downloadencrypt')) {
        if ($_SERVER['ishidden']==4) $files = json_decode('{"folder":{}}', true);
        else $files = fetch_files($path);
    } else {
        $files = fetch_files($path);
    }

    if ($_GET['json']) {
        // return a json
        return files_json($files);
    }
    if (isset($_GET['random'])&&$_GET['random']!=='') {
        if ($_SERVER['ishidden']<4) {
            $tmp = [];
            foreach (array_keys($files['children']) as $filename) {
                if (strtolower(splitlast($filename,'.')[1])==strtolower($_GET['random'])) $tmp[$filename] = $files['children'][$filename][$_SERVER['DownurlStrName']];
            }
            $tmp = array_values($tmp);
            if (count($tmp)>0) {
                $url = $tmp[rand(0,count($tmp)-1)];
                if (isset($_GET['url'])) return output($url, 200);
                $domainforproxy = '';
                $domainforproxy = getConfig('domainforproxy');
                if ($domainforproxy!='') {
                    $url = proxy_replace_domain($url, $domainforproxy);
                }
                return output('', 302, [ 'Location' => $url ]);
            } else return output('',404);
        } else return output('',401);
    }
    if (isset($files['file']) && !isset($_GET['preview'])) {
        // is file && not preview mode
        if ( $_SERVER['ishidden']<4 || (!!getConfig('downloadencrypt')&&$files['name']!=getConfig('passfile')) ) {
            $url = $files[$_SERVER['DownurlStrName']];
            $domainforproxy = '';
            $domainforproxy = getConfig('domainforproxy');
            if ($domainforproxy!='') {
                $url = proxy_replace_domain($url, $domainforproxy);
            }
            if ( strtolower(splitlast($files['name'],'.')[1])=='html' ) return output($files['content']['body'], $files['content']['stat']);
            else {
                if ($_SERVER['HTTP_RANGE']!='') $header['Range'] = $_SERVER['HTTP_RANGE'];
                $header['Location'] = $url;
                return output('', 302, $header);
            }
        }
    }
    if ( isset($files['folder']) || isset($files['file']) ) {
        return render_list($path, $files);
    } else {
        if (!isset($files['error'])) {
            $files['error']['message'] = json_encode($files, JSON_PRETTY_PRINT);
            $files['error']['code'] = 'unknownError';
            $files['error']['stat'] = 500;
        }
        return message('<a href="'.$_SERVER['base_path'].'">'.getconstStr('Back').getconstStr('Home').'</a><div style="margin:8px;"><pre>' . $files['error']['message'] . '</pre></div><a href="javascript:history.back(-1)">'.getconstStr('Back').'</a>', $files['error']['code'], $files['error']['stat']);
    }

    return $files;
}

function tttt($path, $access_token)
{
/*
    $url = 'https://graph.microsoft.com/v1.0/me';
    $arr = curl('GET', $url, '', [ 'Authorization' => 'Bearer ' . $access_token, 'Content-Type' => 'application/json' ], 1);
    //return output($arr['body'] . '<br>' . $access_token);
    $userid = json_decode($arr['body'], true)['id'];

    $url = 'https://graph.microsoft.com/v1.0/users/' . $userid . '/drive';
    $arr = curl('GET', $url, '', [ 'Authorization' => 'Bearer ' . $access_token, 'Content-Type' => 'application/json' ], 1);
    $driveid = json_decode($arr['body'], true)['id'];

    $url = 'https://graph.microsoft.com/v1.0/drives/' . $driveid . '/root/children';
    $arr = curl('GET', $url, '', [ 'Authorization' => 'Bearer ' . $access_token, 'Content-Type' => 'application/json' ], 1);
*/
    //$url = 'https://graph.microsoft.com/v1.0/me/followedSites';
    //$url = 'https://graph.microsoft.com/v1.0/sites/root:/sites/b';
    //$url = 'https://graph.microsoft.com/v1.0/sites/qkq.sharepoint.com,ddb6bb53-910d-410b-b6d0-8614939a9ac1,8a5fc581-b3c6-4f3a-8cec-6b10c10ddae3/drive/';
    //$url = 'https://graph.microsoft.com/v1.0/drives/b!U7u23Q2RC0G20IYUk5qawYHFX4rGszpPjOxrEMEN2uPZ0hEcAMPUQYsh2EnelzXd/root/children';
    $url = 'https://microsoftgraph.chinacloudapi.cn/v1.0/me';
    //$url = 'https://microsoftgraph.chinacloudapi.cn/v1.0/me/followedSites';
    $arr = curl('GET', $url, '', [ 'Authorization' => 'Bearer ' . $access_token, 'Content-Type' => 'application/json' ], 1);

    return output( $url . '<br>' . $arr['stat'] . '<br>' . json_encode(json_decode($arr['body']), JSON_PRETTY_PRINT)  . '<br>' . $access_token );

}

function operate($path)
{
    config_oauth();
    if (!($_SERVER['access_token'] = getcache('access_token', $_SERVER['disktag']))) {
        $refresh_token = getConfig('refresh_token');
        if (!$refresh_token) {
            $html = 'No refresh_token config, please AddDisk again or wait minutes.<br>' . $_SERVER['disktag'];
            $title = 'Error';
            return message($html, $title, 201);
        }
        $response = get_access_token($refresh_token);
        if (isset($response['stat'])) return message($response['body'], 'Error', $response['stat']);
    }

    if ($_SERVER['ajax']) {
        if ($_GET['action']=='del_upload_cache') {
            // del '.tmp' without login. 无需登录即可删除.tmp后缀文件
            error_log('del.tmp:GET,'.json_encode($_GET,JSON_PRETTY_PRINT));
            $tmp = splitlast($_GET['filename'], '/');
            if ($tmp[1]!='') {
                $filename = $tmp[0] . '/.' . $_GET['filelastModified'] . '_' . $_GET['filesize'] . '_' . $tmp[1] . '.tmp';
            } else {
                $filename = '.' . $_GET['filelastModified'] . '_' . $_GET['filesize'] . '_' . $_GET['filename'] . '.tmp';
            }
            $filename = path_format( path_format($_SERVER['list_path'] . path_format($path)) . '/' . spurlencode($filename, '/') );
            $tmp = MSAPI('DELETE', $filename, '', $_SERVER['access_token']);
            $path1 = path_format($_SERVER['list_path'] . path_format($path));
            if ($path1!='/'&&substr($path1,-1)=='/') $path1=substr($path1,0,-1);
            savecache('path_' . $path1, json_decode('{}',true), $_SERVER['disktag'], 1);
            return output($tmp['body'],$tmp['stat']);
        }
        if ($_GET['action']=='uploaded_rename') {
            // rename .scfupload file without login.
            // 无需登录即可重命名.scfupload后缀文件，filemd5为用户提交，可被构造，问题不大，以后处理
            $oldname = spurlencode($_GET['filename']);
            $pos = strrpos($oldname, '.');
            if ($pos>0) $ext = strtolower(substr($oldname, $pos));
            //$oldname = path_format(path_format($_SERVER['list_path'] . path_format($path)) . '/' . $oldname . '.scfupload' );
            $oldname = path_format(path_format($_SERVER['list_path'] . path_format($path)) . '/' . $oldname);
            $data = '{"name":"' . $_GET['filemd5'] . $ext . '"}';
            //echo $oldname .'<br>'. $data;
            $tmp = MSAPI('PATCH',$oldname,$data,$_SERVER['access_token']);
            if ($tmp['stat']==409) {
                MSAPI('DELETE',$oldname,'',$_SERVER['access_token']);
                $tmpbody = json_decode($tmp['body'], true);
                $tmpbody['name'] = $_GET['filemd5'] . $ext;
                $tmp['body'] = json_encode($tmpbody);
            }
            $path1 = path_format($_SERVER['list_path'] . path_format($path));
            if ($path1!='/'&&substr($path1,-1)=='/') $path1=substr($path1,0,-1);
            savecache('path_' . $path1, json_decode('{}',true), $_SERVER['disktag'], 1);
            return output($tmp['body'],$tmp['stat']);
        }
        if ($_GET['action']=='upbigfile') return bigfileupload($path);
    }
    if ($_SERVER['admin']) {
        $tmp = adminoperate($path);
        if ($tmp['statusCode'] > 0) {
            $path1 = path_format($_SERVER['list_path'] . path_format($path));
            if ($path1!='/'&&substr($path1,-1)=='/') $path1=substr($path1,0,-1);
            savecache('path_' . $path1, json_decode('{}',true), $_SERVER['disktag'], 1);
            return $tmp;
        }
    } else {
        if ($_SERVER['ajax']) return output(getconstStr('RefreshtoLogin'),401);
    }
}


function adminoperate($path)
{
    $path1 = path_format($_SERVER['list_path'] . path_format($path));
    if (substr($path1,-1)=='/') $path1=substr($path1,0,-1);
    $tmparr['statusCode'] = 0;
    if (isset($_GET['rename_newname'])&&$_GET['rename_newname']!=$_GET['rename_oldname'] && $_GET['rename_newname']!='') {
        // rename 重命名
        $oldname = spurlencode($_GET['rename_oldname']);
        $oldname = path_format($path1 . '/' . $oldname);
        $data = '{"name":"' . $_GET['rename_newname'] . '"}';
                //echo $oldname;
        $result = MSAPI('PATCH',$oldname,$data,$_SERVER['access_token']);
        //savecache('path_' . $path1, json_decode('{}',true), $_SERVER['disktag'], 1);
        return output($result['body'], $result['stat']);
    }
    if (isset($_GET['delete_name'])) {
        // delete 删除
        $filename = spurlencode($_GET['delete_name']);
        $filename = path_format($path1 . '/' . $filename);
                //echo $filename;
        $result = MSAPI('DELETE', $filename, '', $_SERVER['access_token']);
        //savecache('path_' . $path1, json_decode('{}',true), $_SERVER['disktag'], 1);
        return output($result['body'], $result['stat']);
    }
    if (isset($_GET['operate_action'])&&$_GET['operate_action']==getconstStr('Encrypt')) {
        // encrypt 加密
        if (getConfig('passfile')=='') return message(getconstStr('SetpassfileBfEncrypt'),'',403);
        if ($_GET['encrypt_folder']=='/') $_GET['encrypt_folder']=='';
        $foldername = spurlencode($_GET['encrypt_folder']);
        $filename = path_format($path1 . '/' . $foldername . '/' . getConfig('passfile'));
                //echo $foldername;
        $result = MSAPI('PUT', $filename, $_GET['encrypt_newpass'], $_SERVER['access_token']);
        $path1 = path_format($path1 . '/' . $foldername );
        if ($path1!='/'&&substr($path1,-1)=='/') $path1=substr($path1,0,-1);
        savecache('path_' . $path1 . '/?password', '', $_SERVER['disktag'], 1);
        return output($result['body'], $result['stat']);
    }
    if (isset($_GET['move_folder'])) {
        // move 移动
        $moveable = 1;
        if ($path == '/' && $_GET['move_folder'] == '/../') $moveable=0;
        if ($_GET['move_folder'] == $_GET['move_name']) $moveable=0;
        if ($moveable) {
            $filename = spurlencode($_GET['move_name']);
            $filename = path_format($path1 . '/' . $filename);
            $foldername = path_format('/'.urldecode($path1).'/'.$_GET['move_folder']);
            $data = '{"parentReference":{"path": "/drive/root:'.$foldername.'"}}';
            $result = MSAPI('PATCH', $filename, $data, $_SERVER['access_token']);
            //savecache('path_' . $path1, json_decode('{}',true), $_SERVER['disktag'], 1);
            if ($_GET['move_folder'] == '/../') $path2 = path_format( substr($path1, 0, strrpos($path1, '/')) . '/' );
            else $path2 = path_format( $path1 . '/' . $_GET['move_folder'] . '/' );
            if ($path2!='/'&&substr($path2,-1)=='/') $path2=substr($path2,0,-1);
            savecache('path_' . $path2, json_decode('{}',true), $_SERVER['disktag'], 1);
            return output($result['body'], $result['stat']);
        } else {
            return output('{"error":"'.getconstStr('CannotMove').'"}', 403);
        }
    }
    if (isset($_GET['copy_name'])) {
        // copy 复制
        $filename = spurlencode($_GET['copy_name']);
        $filename = path_format($path1 . '/' . $filename);
        $namearr = splitlast($_GET['copy_name'], '.');
        if ($namearr[0]!='') {
            $newname = $namearr[0] . ' (' . getconstStr('Copy') . ')';
            if ($namearr[1]!='') $newname .= '.' . $namearr[1];
        } else {
            $newname = '.' . $namearr[1] . ' (' . getconstStr('Copy') . ')';
        }
        //$newname = spurlencode($newname);
            //$foldername = path_format('/'.urldecode($path1).'/./');
            //$data = '{"parentReference":{"path": "/drive/root:'.$foldername.'"}}';
        $data = '{ "name": "' . $newname . '" }';
        $result = MSAPI('copy', $filename, $data, $_SERVER['access_token']);
        $num = 0;
        while ($result['stat']==409 && json_decode($result['body'], true)['error']['code']=='nameAlreadyExists') {
            $num++;
            if ($namearr[0]!='') {
                $newname = $namearr[0] . ' (' . getconstStr('Copy') . ' ' . $num . ')';
                if ($namearr[1]!='') $newname .= '.' . $namearr[1];
            } else {
                $newname = '.' . $namearr[1] . ' ('.getconstStr('Copy'). ' ' . $num .')';
            }
            //$newname = spurlencode($newname);
            $data = '{ "name": "' . $newname . '" }';
            $result = MSAPI('copy', $filename, $data, $_SERVER['access_token']);
        }
        //echo $result['stat'].$result['body'];
            //savecache('path_' . $path1, json_decode('{}',true), $_SERVER['disktag'], 1);
            //if ($_GET['move_folder'] == '/../') $path2 = path_format( substr($path1, 0, strrpos($path1, '/')) . '/' );
            //else $path2 = path_format( $path1 . '/' . $_GET['move_folder'] . '/' );
            //savecache('path_' . $path2, json_decode('{}',true), $_SERVER['disktag'], 1);
        return output($result['body'], $result['stat']);
    }
    if (isset($_POST['editfile'])) {
        // edit 编辑
        $data = $_POST['editfile'];
        /*TXT一般不会超过4M，不用二段上传
        $filename = $path1 . ':/createUploadSession';
        $response=MSAPI('POST',$filename,'{"item": { "@microsoft.graph.conflictBehavior": "replace"  }}',$_SERVER['access_token']);
        $uploadurl=json_decode($response,true)['uploadUrl'];
        echo MSAPI('PUT',$uploadurl,$data,$_SERVER['access_token']);*/
        $result = MSAPI('PUT', $path1, $data, $_SERVER['access_token'])['body'];
        //echo $result;
        $resultarry = json_decode($result,true);
        if (isset($resultarry['error'])) return message($resultarry['error']['message']. '<hr><a href="javascript:history.back(-1)">'.getconstStr('Back').'</a>','Error',403);
    }
    if (isset($_GET['create_name'])) {
        // create 新建
        if ($_GET['create_type']=='file') {
            $filename = spurlencode($_GET['create_name']);
            $filename = path_format($path1 . '/' . $filename);
            $result = MSAPI('PUT', $filename, $_GET['create_text'], $_SERVER['access_token']);
        }
        if ($_GET['create_type']=='folder') {
            $data = '{ "name": "' . $_GET['create_name'] . '",  "folder": { },  "@microsoft.graph.conflictBehavior": "rename" }';
            $result = MSAPI('children', $path1, $data, $_SERVER['access_token']);
        }
        //savecache('path_' . $path1, json_decode('{}',true), $_SERVER['disktag'], 1);
        return output($result['body'], $result['stat']);
    }
    if (isset($_GET['RefreshCache'])) {
        $path1 = path_format($_SERVER['list_path'] . path_format($path));
        if ($path1!='/'&&substr($path1,-1)=='/') $path1=substr($path1,0,-1);
        savecache('path_' . $path1 . '/?password', '', $_SERVER['disktag'], 1);
        savecache('customTheme', '', '', 1);
        return message('<meta http-equiv="refresh" content="2;URL=./">', getconstStr('RefreshCache'), 302);
    }
    return $tmparr;
}

function get_access_token($refresh_token)
{
    if (getConfig('Drive_ver')=='shareurl') {
        $shareurl = getConfig('shareurl');
        if (!($_SERVER['sharecookie'] = getConfig('sharecookie'))) {
            $_SERVER['sharecookie'] = curl_request($shareurl, false, [], 1)['returnhead']['Set-Cookie'];
            //$tmp = curl_request($shareurl, false, [], 1);
            //$tmp['body'] .= json_encode($tmp['returnhead'],JSON_PRETTY_PRINT);
            //return $tmp;
            //$_SERVER['sharecookie'] = $tmp['returnhead']['Set-Cookie'];
            //if ($tmp['stat']==302) $url = $tmp['returnhead']['Location'];
            //return curl('GET', $url, [ 'Accept' => 'application/json;odata=verbose', 'Content-Type' => 'application/json;odata=verbose', 'Cookie' => $_SERVER['sharecookie'] ]);
        }
        $tmp1 = splitlast($shareurl, '/')[0];
        $account = splitlast($tmp1, '/')[1];
        $domain = splitlast($shareurl, '/:')[0];
        $response = curl_request(
            $domain . "/personal/" . $account . "/_api/web/GetListUsingPath(DecodedUrl=@a1)/RenderListDataAsStream?@a1='" . urlencode("/personal/" . $account . "/Documents") . "'&RootFolder=" . urlencode("/personal/" . $account . "/Documents/") . "&TryNewExperienceSingle=TRUE",
            '{"parameters":{"__metadata":{"type":"SP.RenderListDataParameters"},"RenderOptions":136967,"AllowMultipleValueFilterForTaxonomyFields":true,"AddRequiredFields":true}}',
            [ 'Accept' => 'application/json;odata=verbose', 'Content-Type' => 'application/json;odata=verbose', 'origin' => $domain, 'Cookie' => $_SERVER['sharecookie'] ]
        );
        if ($response['stat']==200) $ret = json_decode($response['body'], true);
        $_SERVER['access_token'] = splitlast($ret['ListSchema']['.driveAccessToken'],'=')[1];
        $_SERVER['api_url'] = $ret['ListSchema']['.driveUrl'].'/root';
        if (!$_SERVER['access_token']) {
            error_log($domain . "/personal/" . $account . "/_api/web/GetListUsingPath(DecodedUrl=@a1)/RenderListDataAsStream?@a1='" . urlencode("/personal/" . $account . "/Documents") . "'&RootFolder=" . urlencode("/personal/" . $account . "/Documents/") . "&TryNewExperienceSingle=TRUE");
            error_log('failed to get share access_token. response' . json_encode($ret));
            $response['body'] = json_encode(json_decode($response['body']), JSON_PRETTY_PRINT);
            $response['body'] .= '\nfailed to get shareurl access_token.';
            return $response;
            //throw new Exception($response['stat'].', failed to get share access_token.'.$response['body']);
        }
        //$tmp = $ret;
        //$tmp['access_token'] = '******';
        //error_log('['.$_SERVER['disktag'].'] Get access token:'.json_encode($tmp, JSON_PRETTY_PRINT));
        savecache('access_token', $_SERVER['access_token'], $_SERVER['disktag']);
        $tmp1 = null;
        $tmp1['shareapiurl'] = $_SERVER['api_url'];
        $tmp1['sharecookie'] = $_SERVER['sharecookie'];
        if (getConfig('shareapiurl')==''||getConfig('sharecookie')=='') setConfig($tmp1);
    } else {
        $p=0;
        while ($response['stat']==0&&$p<3) {
            $response = curl_request( $_SERVER['oauth_url'] . 'token', 'client_id='. $_SERVER['client_id'] .'&client_secret='. $_SERVER['client_secret'] .'&grant_type=refresh_token&requested_token_use=on_behalf_of&refresh_token=' . $refresh_token );
            $p++;
        }
        if ($response['stat']==200) $ret = json_decode($response['body'], true);
        if (!isset($ret['access_token'])) {
            error_log($_SERVER['oauth_url'] . 'token'.'?client_id='. $_SERVER['client_id'] .'&client_secret='. $_SERVER['client_secret'] .'&grant_type=refresh_token&requested_token_use=on_behalf_of&refresh_token=' . substr($refresh_token, 0, 20) . '******' . substr($refresh_token, -20));
            error_log('failed to get ['.$_SERVER['disktag'].'] access_token. response' . json_encode($ret));
            $response['body'] = json_encode(json_decode($response['body']), JSON_PRETTY_PRINT);
            $response['body'] .= '\nfailed to get ['.$_SERVER['disktag'].'] access_token.';
            return $response;
            //throw new Exception($response['stat'].', failed to get ['.$_SERVER['disktag'].'] access_token.'.$response['body']);
        }
        $tmp = $ret;
        $tmp['access_token'] = '******';
        $tmp['refresh_token'] = '******';
        error_log('['.$_SERVER['disktag'].'] Get access token:'.json_encode($tmp, JSON_PRETTY_PRINT));
        $_SERVER['access_token'] = $ret['access_token'];
        savecache('access_token', $_SERVER['access_token'], $_SERVER['disktag'], $ret['expires_in'] - 300);
        if (time()>getConfig('token_expires')) setConfig([ 'refresh_token' => $ret['refresh_token'], 'token_expires' => time()+7*24*60*60 ]);
    }
    return 0;
}

function config_oauth()
{
    $_SERVER['redirect_uri'] = 'https://scfonedrive.github.io';
    if (getConfig('Drive_ver')=='shareurl') {
        $_SERVER['api_url'] = getConfig('shareapiurl');
        $_SERVER['sharecookie'] = getConfig('sharecookie');
        $_SERVER['DownurlStrName'] = '@content.downloadUrl';
        return 0;
    }
    if (getConfig('Drive_ver')=='MS') {
        // MS
        // https://portal.azure.com
        //$_SERVER['client_id'] = '4da3e7f2-bf6d-467c-aaf0-578078f0bf7c';
        //$_SERVER['client_secret'] = '7/+ykq2xkfx:.DWjacuIRojIaaWL0QI6';
        $_SERVER['client_id'] = '734ef928-d74c-4555-8d1b-d942fa0a1a41';
        $_SERVER['client_secret'] = ':EK[e0/4vQ@mQgma8LmnWb6j4_C1CSIW';
        $_SERVER['oauth_url'] = 'https://login.microsoftonline.com/common/oauth2/v2.0/';
        $_SERVER['scope'] = 'https://graph.microsoft.com/Files.ReadWrite.All https://graph.microsoft.com/Sites.ReadWrite.All offline_access';
        if (getConfig('siteid')) {
            $_SERVER['api_url'] = 'https://graph.microsoft.com/v1.0/sites/' . getConfig('siteid') . '/drive/root';
        } elseif (getConfig('DriveId')) {
            $_SERVER['api_url'] = 'https://graph.microsoft.com/v1.0/drives/' . getConfig('DriveId') . '/root';
        } else {
            $_SERVER['api_url'] = 'https://graph.microsoft.com/v1.0/me/drive/root';
        }
    }
    if (getConfig('Drive_ver')=='CN') {
        // CN 21Vianet
        // https://portal.azure.cn
        //$_SERVER['client_id'] = '04c3ca0b-8d07-4773-85ad-98b037d25631';
        //$_SERVER['client_secret'] = 'h8@B7kFVOmj0+8HKBWeNTgl@pU/z4yLB'; // expire 20200902
        $_SERVER['client_id'] = 'b15f63f5-8b72-48b5-af69-8cab7579bff7';
        $_SERVER['client_secret'] = '0IIuZ1Kcq_YI3NrkZFwsniEo~BoP~8_M22';
        $_SERVER['oauth_url'] = 'https://login.partner.microsoftonline.cn/common/oauth2/v2.0/';
        $_SERVER['scope'] = 'https://microsoftgraph.chinacloudapi.cn/Files.ReadWrite.All https://microsoftgraph.chinacloudapi.cn/Sites.ReadWrite.All offline_access';
        if (getConfig('siteid')) {
            $_SERVER['api_url'] = 'https://microsoftgraph.chinacloudapi.cn/v1.0/sites/' . getConfig('siteid') . '/drive/root';
        } elseif (getConfig('DriveId')) {
            $_SERVER['api_url'] = 'https://microsoftgraph.chinacloudapi.cn/v1.0/drives/' . getConfig('DriveId') . '/root';
        } else {
            $_SERVER['api_url'] = 'https://microsoftgraph.chinacloudapi.cn/v1.0/me/drive/root';
        }
    }

    if (getConfig('Drive_custom')=='on') {
        // Customer
        $_SERVER['client_id'] = getConfig('client_id');
        $_SERVER['client_secret'] = getConfig('client_secret');
    }
    $_SERVER['client_secret'] = urlencode($_SERVER['client_secret']);
    $_SERVER['scope'] = urlencode($_SERVER['scope']);
    $_SERVER['DownurlStrName'] = '@microsoft.graph.downloadUrl';
}

function get_siteid($sharepointSiteAddress, $access_token)
{
    //$sharepointSiteAddress = getConfig('sharepointSiteAddress');
    while (substr($sharepointSiteAddress, -1)=='/') $sharepointSiteAddress = substr($sharepointSiteAddress, 0, -1);
    $tmp = splitlast($sharepointSiteAddress, '/');
    $sharepointname = $tmp[1];
    $tmp = splitlast($tmp[0], '/');
    $sharepointname = $tmp[1] . '/' . $sharepointname;
    if (getConfig('Drive_ver')=='MS') $url = 'https://graph.microsoft.com/v1.0/sites/root:/'.$sharepointname;
    if (getConfig('Drive_ver')=='CN') $url = 'https://microsoftgraph.chinacloudapi.cn/v1.0/sites/root:/'.$sharepointname;

    $i=0;
    $response = [];
    while ($url!=''&&$response['stat']!=200&&$i<4) {
        $response = curl_request($url, false, ['Authorization' => 'Bearer ' . $access_token]);
        $i++;
    }
    if ($response['stat']!=200) {
        error_log('failed to get siteid. response' . json_encode($response));
        $response['body'] .= '\nfailed to get siteid.';
        return $response;
        //throw new Exception($response['stat'].', failed to get siteid.'.$response['body']);
    }
    return json_decode($response['body'],true)['id'];
}

function get_thumbnails_url($path = '/', $location = 0)
{
    $path1 = path_format($path);
    $path = path_format($_SERVER['list_path'] . path_format($path));
    if ($path!='/'&&substr($path,-1)=='/') $path=substr($path,0,-1);
    $thumb_url = getcache('thumb_'.$path, $_SERVER['disktag']);
    if ($thumb_url=='') {
        $url = $_SERVER['api_url'];
        if ($path !== '/') {
            $url .= ':' . $path;
            if (substr($url,-1)=='/') $url=substr($url,0,-1);
        }
        $url .= ':/thumbnails/0/medium';
        $files = json_decode(curl_request($url, false, ['Authorization' => 'Bearer ' . $_SERVER['access_token']])['body'], true);
        if (isset($files['url'])) {
            savecache('thumb_'.$path, $files['url'], $_SERVER['disktag']);
            $thumb_url = $files['url'];
        }
    }
    if ($thumb_url!='') {
        if ($location) {
            $url = $thumb_url;
            $domainforproxy = '';
            $domainforproxy = getConfig('domainforproxy');
            if ($domainforproxy!='') {
                $url = proxy_replace_domain($url, $domainforproxy);
            }
            return output('', 302, [ 'Location' => $url ]);
        } else return output($thumb_url);
    }
    return output('', 404);
}

function bigfileupload($path)
{
    $path1 = path_format($_SERVER['list_path'] . path_format($path));
    if (substr($path1,-1)=='/') $path1=substr($path1,0,-1);
    if ($_GET['upbigfilename']!=''&&$_GET['filesize']>0) {
        $tmp = splitlast($_GET['upbigfilename'], '/');
        if ($tmp[1]!='') {
            $fileinfo['name'] = $tmp[1];
            $fileinfo['path'] = $tmp[0];
        } else {
            $fileinfo['name'] = $_GET['upbigfilename'];
        }
        $fileinfo['size'] = $_GET['filesize'];
        $fileinfo['lastModified'] = $_GET['lastModified'];
        $filename = spurlencode($_GET['upbigfilename'],'/');
        if ($fileinfo['size']>10*1024*1024) {
            $cachefilename = spurlencode( $fileinfo['path'] . '/.' . $fileinfo['lastModified'] . '_' . $fileinfo['size'] . '_' . $fileinfo['name'] . '.tmp', '/');
            $getoldupinfo=fetch_files(path_format($path . '/' . $cachefilename));
            //echo json_encode($getoldupinfo, JSON_PRETTY_PRINT);
            if (isset($getoldupinfo['file'])&&$getoldupinfo['size']<5120) {
                $getoldupinfo_j = curl_request($getoldupinfo[$_SERVER['DownurlStrName']]);
                $getoldupinfo = json_decode($getoldupinfo_j['body'], true);
                if ( json_decode( curl_request($getoldupinfo['uploadUrl'])['body'], true)['@odata.context']!='' ) return output($getoldupinfo_j['body'], $getoldupinfo_j['stat']);
            }
        }
        //if (!$_SERVER['admin']) $filename = spurlencode( $fileinfo['name'] ) . '.scfupload';
        $response = MSAPI('createUploadSession', path_format($path1 . '/' . $filename), '{"item": { "@microsoft.graph.conflictBehavior": "fail"  }}', $_SERVER['access_token']);
        if ($response['stat']<500) {
            $responsearry = json_decode($response['body'],true);
            if (isset($responsearry['error'])) return output($response['body'], $response['stat']);
            $fileinfo['uploadUrl'] = $responsearry['uploadUrl'];
            if ($fileinfo['size']>10*1024*1024) MSAPI('PUT', path_format($path1 . '/' . $cachefilename), json_encode($fileinfo, JSON_PRETTY_PRINT), $_SERVER['access_token']);
        }
        return output($response['body'], $response['stat']);
    }
    return output('error', 400);
}

function MSAPI($method, $path, $data = '', $access_token)
{
    if (substr($path,0,7) == 'http://' or substr($path,0,8) == 'https://') {
        $url=$path;
        $lenth=strlen($data);
        $headers['Content-Length'] = $lenth;
        $lenth--;
        $headers['Content-Range'] = 'bytes 0-' . $lenth . '/' . $headers['Content-Length'];
    } else {
        $url = $_SERVER['api_url'];
        if ($path=='' or $path=='/') {
            $url .= '/';
        } else {
            $url .= ':' . $path;
            if (substr($url,-1)=='/') $url=substr($url,0,-1);
        }
        if ($method=='PUT') {
            if ($path=='' or $path=='/') {
                $url .= 'content';
            } else {
                $url .= ':/content';
            }
            $headers['Content-Type'] = 'text/plain';
        } elseif ($method=='PATCH') {
            $headers['Content-Type'] = 'application/json';
        } elseif ($method=='POST') {
            $headers['Content-Type'] = 'application/json';
        } elseif ($method=='DELETE') {
            $headers['Content-Type'] = 'application/json';
        } else {
            if ($path=='' or $path=='/') {
                $url .= $method;
            } else {
                $url .= ':/' . $method;
            }
            $method='POST';
            $headers['Content-Type'] = 'application/json';
        }
    }
    $headers['Authorization'] = 'Bearer ' . $access_token;
    if (!isset($headers['Accept'])) $headers['Accept'] = '*/*';
    //if (!isset($headers['Referer'])) $headers['Referer'] = $url;*
    $sendHeaders = array();
    foreach ($headers as $headerName => $headerVal) {
        $sendHeaders[] = $headerName . ': ' . $headerVal;
    }
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST,$method);
    curl_setopt($ch, CURLOPT_POSTFIELDS,$data);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    //curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $sendHeaders);
    $response['body'] = curl_exec($ch);
    $response['stat'] = curl_getinfo($ch,CURLINFO_HTTP_CODE);
    //$response['Location'] = curl_getinfo($ch);
    curl_close($ch);
    //error_log($url . "\n" . $response['stat'] . "\n" . $response['body'] . "\n");
    return $response;
}

function fetch_files($path = '/')
{
    global $exts;
    $path1 = path_format($path);
    $path = path_format($_SERVER['list_path'] . path_format($path));
    if ($path!='/'&&substr($path,-1)=='/') $path=substr($path,0,-1);
    if (!($files = getcache('path_' . $path, $_SERVER['disktag']))) {
        // https://docs.microsoft.com/en-us/graph/api/driveitem-get?view=graph-rest-1.0
        // https://docs.microsoft.com/zh-cn/graph/api/driveitem-put-content?view=graph-rest-1.0&tabs=http
        // https://developer.microsoft.com/zh-cn/graph/graph-explorer
        $pos = splitlast($path, '/');
        $parentpath = $pos[0];
        if ($parentpath=='') $parentpath = '/';
        $filename = $pos[1];
        if ($parentfiles = getcache('path_' . $parentpath, $_SERVER['disktag'])) {
            if (isset($parentfiles['children'][$filename][$_SERVER['DownurlStrName']])) {
                if (in_array(splitlast($filename,'.')[1], $exts['txt'])) {
                    if (!(isset($parentfiles['children'][$filename]['content'])&&$parentfiles['children'][$filename]['content']['stat']==200)) {
                        $content1 = curl_request($parentfiles['children'][$filename][$_SERVER['DownurlStrName']]);
                        $parentfiles['children'][$filename]['content'] = $content1;
                        savecache('path_' . $parentpath, $parentfiles, $_SERVER['disktag']);
                    }
                }
                return $parentfiles['children'][$filename];
            }
        }

        $url = $_SERVER['api_url'];
        if ($path !== '/') {
            $url .= ':' . $path;
            if (substr($url,-1)=='/') $url=substr($url,0,-1);
        }
        $url .= '?expand=children(select=id,name,size,file,folder,parentReference,lastModifiedDateTime,'.$_SERVER['DownurlStrName'].')';
        $retry = 0;
        $arr = [];
        while ($retry<3&&!$arr['stat']) {
            $arr = curl_request($url, false, ['Authorization' => 'Bearer ' . $_SERVER['access_token']],1);
            $retry++;
        }
        //error_log($url . '<br>' . $arr['body']);
        if ($arr['stat']<500) {
            $files = json_decode($arr['body'], true);
            //echo $path . '<br><pre>' . json_encode($arr, JSON_PRETTY_PRINT) . '</pre>';
            if (isset($files['folder'])) {
                if ($files['folder']['childCount']>200) {
                    // files num > 200 , then get nextlink
                    $page = $_POST['pagenum']==''?1:$_POST['pagenum'];
                    if ($page>1) $files=fetch_files_children($files, $path1, $page);
                    $files['children'] = children_name($files['children']);
                    /*$url = $_SERVER['api_url'];
                    if ($path !== '/') {
                        $url .= ':' . $path;
                        if (substr($url,-1)=='/') $url=substr($url,0,-1);
                        $url .= ':/children?$top=9999&$select=id,name,size,file,folder,parentReference,lastModifiedDateTime,'.$_SERVER['DownurlStrName'];
                    } else {
                        $url .= '/children?$top=9999&$select=id,name,size,file,folder,parentReference,lastModifiedDateTime,'.$_SERVER['DownurlStrName'];
                    }
                    $children = json_decode(curl_request($url, false, ['Authorization' => 'Bearer ' . $_SERVER['access_token']])['body'], true);
                    $files['children'] = $children['value'];*/
                } else {
                // files num < 200 , then cache
                    //if (isset($files['children'])) {
                        $files['children'] = children_name($files['children']);
                    //}
                    savecache('path_' . $path, $files, $_SERVER['disktag']);
                }
            }
            if (isset($files['file'])) {
                if (in_array(splitlast($files['name'],'.')[1], $exts['txt'])) {
                    if (!(isset($files['content'])&&$files['content']['stat']==200)) {
                        $content1 = curl_request($files[$_SERVER['DownurlStrName']]);
                        $files['content'] = $content1;
                        savecache('path_' . $path, $files, $_SERVER['disktag']);
                    }
                }
            }
            if (isset($files['error'])) {
                $files['error']['stat'] = $arr['stat'];
            }
        } else {
            //error_log($arr['body']);
            $files = json_decode($arr['body'], true);
            if (isset($files['error'])) {
                $files['error']['stat'] = $arr['stat'];
            } else {
                $files['error']['stat'] = 503;
                $files['error']['code'] = 'unknownError';
                $files['error']['message'] = 'unknownError';
            }
            //$files = json_decode( '{"unknownError":{ "stat":'.$arr['stat'].',"message":"'.$arr['body'].'"}}', true);
            //error_log(json_encode($files, JSON_PRETTY_PRINT));
        }
    }

    return $files;
}

function children_name($children)
{
    $tmp = [];
    foreach ($children as $file) {
        $tmp[strtolower($file['name'])] = $file;
    }
    return $tmp;
}

function fetch_files_children($files, $path, $page)
{
    $path1 = path_format($path);
    $path = path_format($_SERVER['list_path'] . path_format($path));
    if ($path!='/'&&substr($path,-1)=='/') $path=substr($path,0,-1);
    $cachefilename = '.SCFcache_'.$_SERVER['function_name'];
    $maxpage = ceil($files['folder']['childCount']/200);
    if (!($files['children'] = getcache('files_' . $path . '_page_' . $page, $_SERVER['disktag']))) {
        // down cache file get jump info. 下载cache文件获取跳页链接
        $cachefile = fetch_files(path_format($path1 . '/' .$cachefilename));
        if ($cachefile['size']>0) {
            $pageinfo = curl_request($cachefile[$_SERVER['DownurlStrName']])['body'];
            $pageinfo = json_decode($pageinfo,true);
            for ($page4=1;$page4<$maxpage;$page4++) {
                savecache('nextlink_' . $path . '_page_' . $page4, $pageinfo['nextlink_' . $path . '_page_' . $page4], $_SERVER['disktag']);
                $pageinfocache['nextlink_' . $path . '_page_' . $page4] = $pageinfo['nextlink_' . $path . '_page_' . $page4];
            }
        }
        $pageinfochange=0;
        for ($page1=$page;$page1>=1;$page1--) {
            $page3=$page1-1;
            $url = getcache('nextlink_' . $path . '_page_' . $page3, $_SERVER['disktag']);
            if ($url == '') {
                if ($page1==1) {
                    $url = $_SERVER['api_url'];
                    if ($path !== '/') {
                        $url .= ':' . $path;
                        if (substr($url,-1)=='/') $url=substr($url,0,-1);
                        $url .= ':/children?$select=id,name,size,file,folder,parentReference,lastModifiedDateTime,'.$_SERVER['DownurlStrName'];
                    } else {
                        $url .= '/children?$select=id,name,size,file,folder,parentReference,lastModifiedDateTime,'.$_SERVER['DownurlStrName'];
                    }
                    $children = json_decode(curl_request($url, false, ['Authorization' => 'Bearer ' . $_SERVER['access_token']])['body'], true);
                    // echo $url . '<br><pre>' . json_encode($children, JSON_PRETTY_PRINT) . '</pre>';
                    savecache('files_' . $path . '_page_' . $page1, $children['value'], $_SERVER['disktag']);
                    $nextlink=getcache('nextlink_' . $path . '_page_' . $page1, $_SERVER['disktag']);
                    if ($nextlink!=$children['@odata.nextLink']) {
                        savecache('nextlink_' . $path . '_page_' . $page1, $children['@odata.nextLink'], $_SERVER['disktag']);
                        $pageinfocache['nextlink_' . $path . '_page_' . $page1] = $children['@odata.nextLink'];
                        $pageinfocache = clearbehindvalue($path,$page1,$maxpage,$pageinfocache);
                        $pageinfochange = 1;
                    }
                    $url = $children['@odata.nextLink'];
                    for ($page2=$page1+1;$page2<=$page;$page2++) {
                        sleep(1);
                        $children = json_decode(curl_request($url, false, ['Authorization' => 'Bearer ' . $_SERVER['access_token']])['body'], true);
                        savecache('files_' . $path . '_page_' . $page2, $children['value'], $_SERVER['disktag']);
                        $nextlink=getcache('nextlink_' . $path . '_page_' . $page2, $_SERVER['disktag']);
                        if ($nextlink!=$children['@odata.nextLink']) {
                            savecache('nextlink_' . $path . '_page_' . $page2, $children['@odata.nextLink'], $_SERVER['disktag']);
                            $pageinfocache['nextlink_' . $path . '_page_' . $page2] = $children['@odata.nextLink'];
                            $pageinfocache = clearbehindvalue($path,$page2,$maxpage,$pageinfocache);
                            $pageinfochange = 1;
                        }
                        $url = $children['@odata.nextLink'];
                    }
                    //echo $url . '<br><pre>' . json_encode($children, JSON_PRETTY_PRINT) . '</pre>';
                    $files['children'] = $children['value'];
                    $files['folder']['page']=$page;
                    $pageinfocache['filenum'] = $files['folder']['childCount'];
                    $pageinfocache['dirsize'] = $files['size'];
                    $pageinfocache['cachesize'] = $cachefile['size'];
                    $pageinfocache['size'] = $files['size']-$cachefile['size'];
                    if ($pageinfochange == 1) MSAPI('PUT', path_format($path.'/'.$cachefilename), json_encode($pageinfocache, JSON_PRETTY_PRINT), $_SERVER['access_token'])['body'];
                    return $files;
                }
            } else {
                for ($page2=$page3+1;$page2<=$page;$page2++) {
                    sleep(1);
                    $children = json_decode(curl_request($url, false, ['Authorization' => 'Bearer ' . $_SERVER['access_token']])['body'], true);
                    savecache('files_' . $path . '_page_' . $page2, $children['value'], $_SERVER['disktag'], 3300);
                    $nextlink=getcache('nextlink_' . $path . '_page_' . $page2, $_SERVER['disktag']);
                    if ($nextlink!=$children['@odata.nextLink']) {
                        savecache('nextlink_' . $path . '_page_' . $page2, $children['@odata.nextLink'], $_SERVER['disktag'], 3300);
                        $pageinfocache['nextlink_' . $path . '_page_' . $page2] = $children['@odata.nextLink'];
                        $pageinfocache = clearbehindvalue($path,$page2,$maxpage,$pageinfocache);
                        $pageinfochange = 1;
                    }
                    $url = $children['@odata.nextLink'];
                }
                //echo $url . '<br><pre>' . json_encode($children, JSON_PRETTY_PRINT) . '</pre>';
                $files['children'] = $children['value'];
                $files['folder']['page']=$page;
                $pageinfocache['filenum'] = $files['folder']['childCount'];
                $pageinfocache['dirsize'] = $files['size'];
                $pageinfocache['cachesize'] = $cachefile['size'];
                $pageinfocache['size'] = $files['size']-$cachefile['size'];
                if ($pageinfochange == 1) MSAPI('PUT', path_format($path.'/'.$cachefilename), json_encode($pageinfocache, JSON_PRETTY_PRINT), $_SERVER['access_token'])['body'];
                return $files;
            }
        }
    } else {
        $files['folder']['page']=$page;
        for ($page4=1;$page4<=$maxpage;$page4++) {
            if (!($url = getcache('nextlink_' . $path . '_page_' . $page4, $_SERVER['disktag']))) {
                if ($files['folder'][$path.'_'.$page4]!='') savecache('nextlink_' . $path . '_page_' . $page4, $files['folder'][$path.'_'.$page4], $_SERVER['disktag']);
            } else {
                $files['folder'][$path.'_'.$page4] = $url;
            }
        }
    }
    return $files;
}

function AddDisk()
{
    global $constStr;
    global $CommonEnv;

    $_SERVER['disktag'] = $_COOKIE['disktag'];
    config_oauth();
    $envs = '';
    foreach ($CommonEnv as $env) $envs .= '\'' . $env . '\', ';
    $url = path_format($_SERVER['PHP_SELF'] . '/');
    $api_url = splitfirst($_SERVER['api_url'], '/v1.0')[0] . '/v1.0';

    if (isset($_GET['install4'])) {
        if (!($_SERVER['access_token'] = getcache('access_token', $_SERVER['disktag']))) {
            $refresh_token = getConfig('refresh_token');
            if (!$refresh_token) {
                $html = 'No refresh_token config, please AddDisk again or wait minutes.<br>' . $_SERVER['disktag'];
                $title = 'Error';
                return message($html, $title, 201);
            }
            $response = get_access_token($refresh_token);
            if (isset($response['stat'])) return message($response['body'], 'Error', $response['stat']);
        }
        $access_token = $_SERVER['access_token'];

        $tmp = null;
        if ($_POST['DriveType']=='Onedrive') {
            $api = $api_url . '/me';
            $arr = curl('GET', $api, '', [ 'Authorization' => 'Bearer ' . $access_token ], 1);
            if ($arr['stat']==200) {
                $userid = json_decode($arr['body'], true)['id'];
                $api = $api_url . '/users/' . $userid . '/drive';
                $arr = curl('GET', $api, '', [ 'Authorization' => 'Bearer ' . $access_token ], 1);
                if ($arr['stat']!=200) return message($arr['stat'] . '<br>' . $api . '<br>' . $arr['body'], 'Get User Drive ID', $arr['stat']);
                $tmp['DriveId'] = json_decode($arr['body'], true)['id'];
            } elseif ($arr['stat']==403) {
                $api = $api_url . '/me/drive';
            } else {
                return message($arr['stat'] . $arr['body'], 'Get User ID', $arr['stat']);
            }
        } elseif ($_POST['DriveType']=='Custom') {
            // sitename计算siteid
            $tmp1 = get_siteid($_POST['sharepointSiteAddress'], $access_token);
            if (isset($tmp1['stat'])) return message($arr['stat'] . $tmp1['body'], 'Get Sharepoint Site ID ' . $_POST['sharepointSiteAddress'], $tmp1['stat']);
            $siteid = $tmp1;
            //$api = $api_url . '/sites/' . $siteid . '/drive/';
            //$arr = curl('GET', $api, '', [ 'Authorization' => 'Bearer ' . $access_token ], 1);
            //if ($arr['stat']!=200) return message($arr['stat'] . $arr['body'], 'Get Sharepoint Drive ID ' . $_POST['DriveType'], $arr['stat']);
            $tmp['siteid'] = $siteid;
            //$tmp['DriveId'] = json_decode($arr['body'], true)['id'];
        } else {
            // 直接是siteid
            //$api = $api_url . '/sites/' . $_POST['DriveType'] . '/drive/';
            //$arr = curl('GET', $api, '', [ 'Authorization' => 'Bearer ' . $access_token ], 1);
            //if ($arr['stat']!=200) return message($arr['stat'] . $arr['body'], 'Get Sharepoint Drive ID ' . $_POST['DriveType'], $arr['stat']);
            $tmp['siteid'] = $_POST['DriveType'];
            //$tmp['DriveId'] = json_decode($arr['body'], true)['id'];
        }

        $response = setConfigResponse( setConfig($tmp, $_SERVER['disktag']) );
        if (api_error($response)) {
            $html = api_error_msg($response);
            $title = 'Error';
            return message($html, $title, 201);
        } else {
            $str .= $driveid . '
            <meta http-equiv="refresh" content="5;URL=' . $url . '">';
            return message($str, getconstStr('WaitJumpIndex'), 201);
        }
    }

    if (isset($_GET['install3'])) {
        if (!($_SERVER['access_token'] = getcache('access_token', $_SERVER['disktag']))) {
            $refresh_token = getConfig('refresh_token');
            if (!$refresh_token) {
                $html = 'No refresh_token config, please AddDisk again or wait minutes.';
                $title = 'Error';
                return message($html, $title, 201);
            }
            $response = get_access_token($refresh_token);
            if (isset($response['stat'])) return message($response['body'], 'Error', $response['stat']);
        }
        $access_token = $_SERVER['access_token'];

        $api = $api_url . '/me/followedSites';
        $arr = curl('GET', $api, '', [ 'Authorization' => 'Bearer ' . $access_token ]);
        if (!($arr['stat']==200||$arr['stat']==403)) return message($arr['stat'] . json_encode(json_decode($arr['body']), JSON_PRETTY_PRINT), 'Get followedSites', $arr['stat']);
        $sites = json_decode($arr['body'], true)['value'];

        $title = 'Select Disk';
        $html = '
<div>
    <form action="?AddDisk&Driver=MS365&install4" method="post" onsubmit="return notnull(this);">
        <label><input type="radio" name="DriveType" value="Onedrive" checked>' . 'Use Onedrive ' . getconstStr(' ') . '</label><br>';
        if ($sites[0]!='') foreach ($sites as $k => $v) {
            $html .= '
        <label>
            <input type="radio" name="DriveType" value="' . $v['id'] . '">' . 'Use Sharepoint: <br><div style="width:100%;margin:0px 35px">webUrl: ' . $v['webUrl'] . '<br>siteid: ' . $v['id'] . '</div>
        </label>';
        }
        $html .= '
        <label>
            <input type="radio" name="DriveType" value="Custom" id="Custom">' . 'Use Other Sharepoint:' . getconstStr(' ') . '<br>
            <div style="width:100%;margin:0px 35px">' . getconstStr('GetSharepointSiteAddress') . '<br>
                <input type="text" name="sharepointSiteAddress" style="width:100%;" placeholder="' . getconstStr('InputSharepointSiteAddress') . '" onclick="document.getElementById(\'Custom\').checked=\'checked\';">
            </div>
        </label><br>
        ';
        $html .= '
        <input type="submit" value="' . getconstStr('Submit') . '">
    </form>
</div>
<script>
        function notnull(t)
        {
            if (t.DriveType.value==\'\') {
                    alert(\'Select a Disk\');
                    return false;
            }
            if (t.DriveType.value==\'Custom\') {
                if (t.sharepointSiteAddress.value==\'\') {
                    alert(\'sharepoint Site Address\');
                    return false;
                }
            }
            return true;
        }
    </script>
    ';
        return message($html, $title, 201);
    }

    if (isset($_GET['install2']) && isset($_GET['code'])) {
        $tmp = curl_request($_SERVER['oauth_url'] . 'token', 'client_id=' . $_SERVER['client_id'] .'&client_secret=' . $_SERVER['client_secret'] . '&grant_type=authorization_code&requested_token_use=on_behalf_of&redirect_uri=' . $_SERVER['redirect_uri'] . '&code=' . $_GET['code']);
        if ($tmp['stat']==200) $ret = json_decode($tmp['body'], true);
        if (isset($ret['refresh_token'])) {
            $refresh_token = $ret['refresh_token'];
            $str = '
        refresh_token :<br>';
            $str .= '
        <textarea readonly style="width: 95%">' . $refresh_token . '</textarea><br><br>
        ' . getconstStr('SavingToken') . '
        <script>
            var texta=document.getElementsByTagName(\'textarea\');
            for(i=0;i<texta.length;i++) {
                texta[i].style.height = texta[i].scrollHeight + \'px\';
            }
            document.cookie=\'language=; path=/\';
        </script>';
            $tmptoken['refresh_token'] = $refresh_token;
            $tmptoken['token_expires'] = time()+7*24*60*60;
            $response = setConfigResponse( setConfig($tmptoken, $_COOKIE['disktag']) );
            if (api_error($response)) {
                $html = api_error_msg($response);
                $title = 'Error';
                return message($html, $title, 201);
            } else {
                savecache('access_token', $ret['access_token'], $_COOKIE['disktag'], $ret['expires_in'] - 60);
                $str .= '
                <meta http-equiv="refresh" content="3;URL=' . $url . '?AddDisk&Driver=MS365&install3">';
                return message($str, getconstStr('Wait') . ' 3s', 201);
            }
        }
        return message('<pre>' . json_encode(json_decode($tmp['body']), JSON_PRETTY_PRINT) . '</pre>', $tmp['stat']);
        //return message('<pre>' . json_encode($ret, JSON_PRETTY_PRINT) . '</pre>', 500);
    }

    if (isset($_GET['install1'])) {
        if (getConfig('Drive_ver')=='MS' || getConfig('Drive_ver')=='CN') {
            return message('
    <a href="" id="a1">'.getconstStr('JumptoOffice').'</a>
    <script>
        url=location.protocol + "//" + location.host + "' . $url . '";
        url="' . $_SERVER['oauth_url'] . 'authorize?scope=' . $_SERVER['scope'] . '&response_type=code&client_id=' . $_SERVER['client_id'] . '&redirect_uri=' . $_SERVER['redirect_uri'] . '&state=' . '"+encodeURIComponent(url);
        document.getElementById(\'a1\').href=url;
        //window.open(url,"_blank");
        location.href = url;
    </script>
    ', getconstStr('Wait') . ' 1s', 201);
        } else {
            return message('Something error, retry after a few seconds.', 'retry', 201);
        }
    }

    if (isset($_GET['install0'])) {
        if ($_POST['disktag_add']!='') {
            if (in_array($_POST['disktag_add'], $CommonEnv)) {
                return message('Do not input ' . $envs . '<br><button onclick="location.href = location.href;">'.getconstStr('Refresh').'</button><script>document.cookie=\'disktag=; path=/\';</script>', 'Error', 201);
            }
            $_SERVER['disktag'] = $_POST['disktag_add'];
            $tmp['disktag_add'] = $_POST['disktag_add'];
            $tmp['diskname'] = $_POST['diskname'];
            $tmp['Driver'] = 'MS365';
            $tmp['Drive_ver'] = $_POST['Drive_ver'];
            if ($_POST['Drive_ver']=='shareurl') {
                $tmp['shareurl'] = $_POST['shareurl'];
                $tmp['refresh_token'] = 1;
            } else {
                if ($_POST['Drive_ver']=='MS'&&$_POST['NT_Drive_custom']=='on') {
                    $tmp['Drive_custom'] = $_POST['NT_Drive_custom'];
                    $tmp['client_id'] = $_POST['NT_client_id'];
                    $tmp['client_secret'] = $_POST['NT_client_secret'];
                } elseif ($_POST['Drive_ver']=='CN'&&$_POST['CN_Drive_custom']=='on') {
                    $tmp['Drive_custom'] = $_POST['CN_Drive_custom'];
                    $tmp['client_id'] = $_POST['CN_client_id'];
                    $tmp['client_secret'] = $_POST['CN_client_secret'];
                } else {
                    $tmp['Drive_custom'] = '';
                    $tmp['client_id'] = '';
                    $tmp['client_secret'] = '';
                }
                if ($_POST['usesharepoint']=='on') {
                    $tmp['usesharepoint'] = $_POST['usesharepoint'];
                    $tmp['sharepointSiteAddress'] = $_POST['sharepointSiteAddress'];
                } else {
                    $tmp['usesharepoint'] = '';
                    $tmp['sharepointSiteAddress'] = '';
                }
            }
            $response = setConfigResponse( setConfig($tmp, $_COOKIE['disktag']) );
            if (api_error($response)) {
                $html = api_error_msg($response);
                $title = 'Error';
            } else {
                $title = getconstStr('MayinEnv');
                $html = getconstStr('Wait') . ' 3s<meta http-equiv="refresh" content="3;URL=' . $url . '?AddDisk&Driver=MS365&install1">';
                if ($_POST['Drive_ver']=='shareurl') $html = getconstStr('Wait') . ' 3s<meta http-equiv="refresh" content="3;URL=' . $url . '">';
            }
            return message($html, $title, 201);
        }
    }

    //if ($constStr['language']!='zh-cn') {
    //    $linklang='en-us';
    //} else $linklang='zh-cn';
    //$ru = "https://developer.microsoft.com/".$linklang."/graph/quick-start?appID=_appId_&appName=_appName_&redirectUrl=".$_SERVER['redirect_uri']."&platform=option-php";
    //$deepLink = "/quickstart/graphIO?publicClientSupport=false&appName=OneManager&redirectUrl=".$_SERVER['redirect_uri']."&allowImplicitFlow=false&ru=".urlencode($ru);
    //$app_url = "https://apps.dev.microsoft.com/?deepLink=".urlencode($deepLink);
    $html = '
<div>
    <form action="?AddDisk&Driver=MS365&install0" method="post" onsubmit="return notnull(this);">
        ' . getconstStr('OnedriveDiskTag') . ': (' . getConfig('disktag') . ')
        <input type="text" name="disktag_add" placeholder="' . getconstStr('EnvironmentsDescription')['disktag'] . '" style="width:100%"><br>
        ' . getconstStr('OnedriveDiskName') . ':
        <input type="text" name="diskname" placeholder="' . getconstStr('EnvironmentsDescription')['diskname'] . '" style="width:100%"><br>
        <br>
        <div>
            <label><input type="radio" name="Drive_ver" value="MS" onclick="document.getElementById(\'NT_custom\').style.display=\'\';document.getElementById(\'CN_custom\').style.display=\'none\';document.getElementById(\'inputshareurl\').style.display=\'none\';">MS: ' . getconstStr('DriveVerMS') . '</label><br>
            <div id="NT_custom" style="display:none;margin:0px 35px">
                <label><input type="checkbox" name="NT_Drive_custom" onclick="document.getElementById(\'NT_secret\').style.display=(this.checked?\'\':\'none\');">' . getconstStr('CustomIdSecret') . '</label><br>
                <div id="NT_secret" style="display:none;margin:10px 35px">
                    <a href="https://portal.azure.com/#blade/Microsoft_AAD_IAM/ActiveDirectoryMenuBlade/RegisteredApps" target="_blank">' . getconstStr('GetSecretIDandKEY') . '</a><br>
                    return uri: https://scfonedrive.github.io/<br>
                    client_id:<input type="text" name="NT_client_id" style="width:100%" placeholder="a1b2c345-90ab-cdef-ghij-klmnopqrstuv"><br>
                    client_secret:<input type="text" name="NT_client_secret" style="width:100%"><br>
                </div>
            </div><br>
            <label><input type="radio" name="Drive_ver" value="CN" onclick="document.getElementById(\'CN_custom\').style.display=\'\';document.getElementById(\'NT_custom\').style.display=\'none\';document.getElementById(\'inputshareurl\').style.display=\'none\';">CN: ' . getconstStr('DriveVerCN') . '</label><br>
            <div id="CN_custom" style="display:none;margin:0px 35px">
                <label><input type="checkbox" name="CN_Drive_custom" onclick="document.getElementById(\'CN_secret\').style.display=(this.checked?\'\':\'none\');">' . getconstStr('CustomIdSecret') . '</label><br>
                <div id="CN_secret" style="display:none;margin:10px 35px">
                    <a href="https://portal.azure.cn/#blade/Microsoft_AAD_IAM/ActiveDirectoryMenuBlade/RegisteredApps" target="_blank">' . getconstStr('GetSecretIDandKEY') . '</a><br>
                    return uri:<br>https://scfonedrive.github.io/<br>
                    client_id:<input type="text" name="CN_client_id" style="width:100%" placeholder="a1b2c345-90ab-cdef-ghij-klmnopqrstuv"><br>
                    client_secret:<input type="text" name="CN_client_secret" style="width:100%"><br>
                </div>
            </div><br>
            <label><input type="radio" name="Drive_ver" value="shareurl" onclick="document.getElementById(\'CN_custom\').style.display=\'none\';document.getElementById(\'inputshareurl\').style.display=\'\';document.getElementById(\'NT_custom\').style.display=\'none\';">ShareUrl: ' . getconstStr('DriveVerShareurl') . '</label><br>
            <div id="inputshareurl" style="display:none;margin:0px 35px">
                ' . getconstStr('UseShareLink') . '
                <input type="text" name="shareurl" style="width:100%" placeholder="https://xxxx.sharepoint.com/:f:/g/personal/xxxxxxxx/mmmmmmmmm?e=XXXX"><br>
            </div>
        </div>
        <br>

        <input type="submit" value="' . getconstStr('Submit') . '">
    </form>
</div>
    <script>
        function notnull(t)
        {
            if (t.disktag_add.value==\'\') {
                alert(\'' . getconstStr('OnedriveDiskTag') . '\');
                return false;
            }
            envs = [' . $envs . '];
            if (envs.indexOf(t.disktag_add.value)>-1) {
                alert("Do not input ' . $envs . '");
                return false;
            }
            var reg = /^[a-zA-Z]([-_a-zA-Z0-9]{1,20})$/;
            if (!reg.test(t.disktag_add.value)) {
                alert(\'' . getconstStr('TagFormatAlert') . '\');
                return false;
            }
            if (t.Drive_ver.value==\'\') {
                    alert(\'Select a Driver\');
                    return false;
            }
            if (t.Drive_ver.value==\'shareurl\') {
                if (t.shareurl.value==\'\') {
                    alert(\'shareurl\');
                    return false;
                }
            } else {
                if ((t.Drive_ver.value==\'MS\') && t.NT_Drive_custom.checked==true) {
                    if (t.NT_client_secret.value==\'\'||t.NT_client_id.value==\'\') {
                        alert(\'client_id & client_secret\');
                        return false;
                    }
                }
                if ((t.Drive_ver.value==\'CN\') && t.CN_Drive_custom.checked==true) {
                    if (t.CN_client_secret.value==\'\'||t.CN_client_id.value==\'\') {
                        alert(\'client_id & client_secret\');
                        return false;
                    }
                }
            }
            var expd = new Date();
            expd.setTime(expd.getTime()+(2*60*60*1000));
            var expires = "expires="+expd.toGMTString();
            document.cookie=\'disktag=\'+t.disktag_add.value+\'; path=/; \'+expires;
            return true;
        }
    </script>';
    $title = 'Select Account Type';
    return message($html, $title, 201);
}
