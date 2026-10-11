<?php

if(!function_exists('bx_root')) {
function bx_root($directory) {
    for($i=0;$i<6;$i++){if(is_file($directory.'/functions/function.php'))return $directory;$directory=dirname($directory);}
    throw new RuntimeException('Keep this script inside the Buxads-scripts folder.');
}
function bx_storage_root($root,$home,$platform) {
    return $platform!=='Windows'&&is_string($home)&&$home!==''?rtrim($home,'/').'/.buxads':$root;
}
function bx_initialize($root,$storage=null) {
    if(defined('BUXADS_DATA_DIR'))return BUXADS_DATA_DIR;
    $data=$storage??bx_storage_root($root,getenv('HOME'),PHP_OS_FAMILY);
    foreach(['information','configs'] as $folder){
        $target=$data.'/'.$folder;
        if(!is_dir($target)&&!@mkdir($target,0700,true)&&!is_dir($target))throw new RuntimeException('Cannot create private storage: '.$target);
        if($data!==$root&&is_dir($root.'/'.$folder)&&!is_file($data.'/.migrated')){
            $source=$root.'/'.$folder;
            $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source,FilesystemIterator::SKIP_DOTS));
            foreach($iterator as $file){
                if(!$file->isFile()||$file->isLink())continue;
                $relative=substr($file->getPathname(),strlen($source)+1);
                if(str_starts_with(str_replace('\\','/',$relative),'instant-backups/'))continue;
                $destination=$target.'/'.$relative;if(file_exists($destination))continue;
                $parent=dirname($destination);if(!is_dir($parent)&&!@mkdir($parent,0700,true)&&!is_dir($parent))throw new RuntimeException('Cannot create storage folder.');
                if(!@copy($file->getPathname(),$destination))throw new RuntimeException('Cannot migrate existing saved files.');
                @chmod($destination,0600);
            }
        }
    }
    if($data!==$root&&!is_file($data.'/.migrated'))file_put_contents($data.'/.migrated','1',LOCK_EX);
    define('BUXADS_DATA_DIR',$data);return $data;
}
function bx_card($title,$rows,$color="\033[96m") {
    $width=max(38,min(68,(int)(getenv('COLUMNS')?:68)));$inner=$width-4;
    echo $color.'╭'.str_repeat('─',$width-2)."╮\n";
    foreach(array_merge([strtoupper($title),''],(array)$rows) as $row){
        foreach(explode("\n",wordwrap((string)$row,$inner,"\n",true)) as $line)echo $color.'│ '."\033[97m".str_pad($line,$inner).$color." │\n";
    }
    echo $color.'╰'.str_repeat('─',$width-2)."╯\033[0m\n";
}
}


/** Instant sites - BuxAds shared runner. @version 1.2.0 */
if (!function_exists('fp_main')) {
function fp_site() {return $GLOBALS['fp_site_config']??['host'=>'faucetpepe.xyz','title'=>'FaucetPepe','mode'=>'form','coins'=>['ltc']];}
function fp_host() {return fp_site()['host'];}
function fp_origin() {return 'https://'.fp_host();}
function fp_private() {return bx_initialize(fp_root()).'/configs/'.(fp_host()==='faucetpepe.xyz'?'faucetpepe-client':fp_host().'-instant-client');}
function fp_coin($a) {return $a['currency']??($GLOBALS['fp_currency']??'ltc');}
function fp_points_label() {return in_array(fp_host(),['spaceshooter.net','gamefaucet.fun','claimpepe.xyz'],true)?'ENERGY':'PTS';}
function fp_card($title,$rows,$color=null) {
    bx_card($title,$rows,$color??CYAN);
}
function fp_banner($accounts) {
    $form=(fp_site()['mode']??'form')==='form';
    fp_card(fp_site()['title'].' / BuxAds',[
        $form?'FAUCET  +  SHORTLINKS':'FAUCET',fp_host().'  |  '.count($accounts).' accounts  |  '.strtoupper($GLOBALS['fp_currency']??'ltc'),
        $form?'Automatic compulsory links  |  Separate account sessions':'Separate account sessions  |  Stop after 3 failures',
        $form?'Adlink  /  Shrinkme  /  Shrinkearn  /  Clksh':'Server-confirmed rewards']);
}
function fp_root() {
    $dir=__DIR__;
    for($i=0;$i<5;$i++) {if(is_file($dir.'/functions/function.php'))return $dir;$dir=dirname($dir);}
    throw new RuntimeException('Save this script inside Buxads-scripts.');
}
function fp_log($level,$message,$label='') {
    $color=['OK'=>GREEN,'INFO'=>CYAN,'WAIT'=>YELLOW,'ERROR'=>RED][$level]??WHITE;
    $line=sprintf('[%s] %-5s %-12s %s',date('H:i:s'),$level,$label,preg_replace('/[\x00-\x1f\x7f]/',' ',(string)$message));
    echo $color.$line."\033[0m\n";
    file_put_contents(fp_private().'/events.log',$line.PHP_EOL,FILE_APPEND|LOCK_EX);
}
function fp_accounts_file() {return bx_initialize(fp_root()).'/information/'.fp_host().'.txt';}
function fp_accounts() {
    if(!is_file(fp_accounts_file()))return [];$accounts=[];
    foreach(file(fp_accounts_file(),FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) as $line) {
        if(str_starts_with(trim($line),'#'))continue;$p=explode('|',trim($line));
        if(filter_var($p[0]??'',FILTER_VALIDATE_EMAIL))$accounts[]=['email'=>$p[0],'password'=>$p[1]??'','proxy'=>$p[2]??'','label'=>'Account '.(count($accounts)+1)];
    }return $accounts;
}
function fp_save_accounts($accounts) {
    $file=fp_accounts_file();$directory=dirname($file);
    if(!is_dir($directory)&&!@mkdir($directory,0700,true)&&!is_dir($directory))throw new RuntimeException('Cannot create account folder: '.$directory.'. Move Buxads-scripts into a writable folder in Termux home.');
    $text="# email|password(optional)|proxy(optional)\n";
    foreach($accounts as $a)$text.=$a['email'].'|'.($a['password']??'').'|'.($a['proxy']??'')."\n";
    if(@file_put_contents($file,$text,LOCK_EX)===false)throw new RuntimeException('Cannot save accounts to '.$file.'. Check folder permissions, or move Buxads-scripts into Termux home.');
    @chmod($file,0600);
}
function fp_ua() {
    $file=bx_initialize(fp_root()).'/configs/'.fp_host().'-config/userAgent';
    return is_file($file)&&trim(file_get_contents($file))!==''?trim(file_get_contents($file)):trim(saveData(APP_HOST,'user-agent'));
}
function fp_cookie_name($a) {return ($a['session_prefix']??'fp-').md5(strtolower($a['email']));}
function fp_identity_file($a) {return fp_private().'/'.fp_cookie_name($a).'.json';}
function fp_request($path,$a,$fields=null,$external=false,$json=false) {
    $url=fp_origin().$path;
    for($i=0;$i<6;$i++) {
        $headers=['accept: text/html,application/xhtml+xml,application/json','accept-language: en-US,en;q=0.9','user-agent: '.fp_ua()];
        if($fields!==null)$headers=array_merge($headers,['content-type: '.($json?'application/json':'application/x-www-form-urlencoded'),'origin: '.fp_origin(),'referer: '.fp_origin().'/']);
        $r=Run($url,$headers,$fields!==null?($json?json_encode($fields):http_build_query($fields)):null,'data',$a['proxy']?:null,2,fp_cookie_name($a));
        $status=(int)($r['info']['http_code']??0);
        if($status===403&&(str_contains($r['body']??'','Just a moment...')||str_contains($r['body']??'','cf-chl-')))throw new RuntimeException('Cloudflare browser verification required at '.parse_url($url,PHP_URL_PATH).'; account stopped for this run.');
        if($status===401&&$path==='/api/auth/me'){$r['url']=$url;return $r;}
        if(!$status||$status>=400)throw new RuntimeException('HTTP '.$status.' at '.parse_url($url,PHP_URL_PATH));
        $next=$r['info']['redirect_url']??'';
        if($status>=300&&$status<400&&$next) {
            if(str_starts_with($next,'/'))$next=fp_origin().$next;
            if(parse_url($next,PHP_URL_HOST)!==fp_host()) {
                if(!$external||!fp_public_url($next))throw new RuntimeException('External redirect stopped.');
                $r['url']=$url;$r['external_url']=$next;return $r;
            }
            if(parse_url($next,PHP_URL_SCHEME)!=='https')throw new RuntimeException('Insecure site redirect stopped.');
            $url=$next;if(in_array($status,[301,302,303],true))$fields=null;continue;
        }$r['url']=$url;return $r;
    }throw new RuntimeException('Too many redirects.');
}
function fp_xpath($html) {
    $old=libxml_use_internal_errors(true);$doc=new DOMDocument();$doc->loadHTML('<?xml encoding="UTF-8">'.$html);
    libxml_clear_errors();libxml_use_internal_errors($old);return new DOMXPath($doc);
}
function fp_form($html,$path) {
    $xp=fp_xpath($html);
    foreach($xp->query('//form[@action]') as $form) {
        if(parse_url($form->getAttribute('action'),PHP_URL_PATH)!==$path)continue;$fields=[];
        foreach($xp->query('.//input[@name]',$form) as $n)if(!$n->hasAttribute('disabled')&&!in_array($n->getAttribute('type'),['submit','button'],true))$fields[$n->getAttribute('name')]=$n->getAttribute('value');
        return $fields;
    }throw new RuntimeException('Form missing: '.$path);
}
function fp_sitekey($html,$class) {
    $nodes=fp_xpath($html)->query('//*[contains(concat(" ",normalize-space(@class)," ")," '.$class.' ")][@data-sitekey]');
    return $nodes->length?$nodes->item(0)->getAttribute('data-sitekey'):'';
}
function fp_wait($html) {
    return preg_match('/(?:let|var|const)\s+wait\s*=\s*(\d+)(?:\s*-\s*(\d+))?\s*;/',$html,$m)?max(0,(int)$m[1]-(int)($m[2]??0))+1:0;
}
function fp_message($html) {
    if(preg_match('/(?:Swal\.fire|swal)\s*\(\s*\{([\s\S]*?)\}\s*\)/',$html,$b)&&preg_match('/\btext\s*:\s*([\x27\x22])((?:\\\\.|(?!\1).)*)\1/s',$b[1],$m))return html_entity_decode(stripslashes($m[2]),ENT_QUOTES|ENT_HTML5);
    return '';
}
function fp_reward($html) {
    if(!preg_match('/([\d.]+)\s+([a-z]+)\s*&\s*(\d+)\s+(PTS|energy) has been sent to your account/i',fp_message($html),$m))return null;
    return ['amount'=>$m[1],'currency'=>strtolower($m[2]),'points'=>(int)$m[3],'points_label'=>strtoupper($m[4])];
}
function fp_token($r) {
    $token=is_array($r)?($r['token']??$r['request']??''):'';
    if(!is_string($token)||!$token||(isset($r['status'])&&(int)$r['status']!==1)||str_starts_with($token,'ERROR_')||$token==='TIMEOUT') {
        $code=$r['request']??$r['response']['request']??'SOLVE_FAILED';
        throw new RuntimeException('Captcha: '.(is_string($code)&&preg_match('/^[A-Z_]+$/',$code)?$code:'SOLVE_FAILED'));
    }return $token;
}
function fp_adslab($key,$label) {
    for($attempt=1;$attempt<=2;$attempt++) {
        try{return fp_token(solve_adslab($key,fp_host()));}
        catch(RuntimeException $e) {
            if($attempt===2||$e->getMessage()!=='Captcha: ERROR_CAPTCHA_UNSOLVABLE')throw $e;
            fp_log('WAIT','Captcha unsolvable; retrying once before submitting a claim.',$label);sleep(3);
        }
    }
}
function fp_identity($a) {
    $r=Run('http://ip-api.com/json/?fields=query,timezone',[],null,'data',$a['proxy']?:null,2,fp_cookie_name($a));$ip=json_decode($r['body']??'',true);
    if(empty($ip['query'])||empty($ip['timezone']))throw new RuntimeException('IP/timezone lookup failed.');
    $ls='en-US,en';$utt=$ip['timezone'];return ['uf'=>md5($ip['query'].fp_ua().$ls.$utt),'utt'=>$utt,'ls'=>$ls];
}
function fp_login($a,$force=false) {
    if((fp_site()['mode']??'form')==='ourcoin'){fp_ourcoin_login($a);return;}
    if((fp_site()['mode']??'form')==='lime'){fp_lime_login($a);return;}
    $r=fp_request('/',$a);if(!$force&&str_contains($r['url'],'/dashboard'))return;
    $fields=fp_form($r['body'],'/auth/login');$key=fp_sitekey($r['body'],'h-captcha');if(!$key)throw new RuntimeException('hCaptcha sitekey missing.');
    $identity=fp_identity($a);fp_log('INFO','Login captcha...',$a['label']);
    $token=fp_token(captcha(fp_origin().'/',$key,'hcaptcha'));
    $fields=array_replace($fields,$identity,['email'=>$a['email'],'captcha'=>'hcaptcha','g-recaptcha-response'=>$token,'h-captcha-response'=>$token]);
    $r=fp_request('/auth/login',$a,$fields);if(!str_contains($r['url'],'/dashboard'))throw new RuntimeException('Login not confirmed: '.(fp_message($r['body'])?:'dashboard missing'));
    file_put_contents(fp_identity_file($a),json_encode($identity+['_ua'=>fp_ua()]),LOCK_EX);fp_log('OK','MD5 login confirmed.',$a['label']);
}
function fp_claim($a) {
    if((fp_site()['mode']??'form')==='ourcoin')return fp_ourcoin_claim($a);
    if((fp_site()['mode']??'form')==='lime')return fp_lime_claim($a);
    $coin=fp_coin($a);$links=[];
    if(!is_file(fp_identity_file($a)))fp_login($a);
    for($gate=0;$gate<=3;$gate++) {
        try{$r=fp_request('/faucet/'.$coin,$a);}
        catch(RuntimeException $e){if(!str_starts_with($e->getMessage(),'HTTP 403 at /faucet/'))throw $e;fp_login($a);$r=fp_request('/faucet/'.$coin,$a);}
        $path=parse_url($r['url'],PHP_URL_PATH);
        if(str_starts_with($path,'/links/')) {
            if($gate===3)throw new RuntimeException('Still requires a shortlink after three completions; stopping to avoid an endless loop.');
            fp_log('WAIT','Compulsory shortlink; completing it before the faucet.',$a['label']);
            $links[]=fp_shortlink($a,$coin);continue;
        }
        if(!str_starts_with($path,'/faucet/')){fp_login($a);continue;}
        break;
    }
    if($wait=fp_wait($r['body']))return ['wait'=>$wait,'reward'=>null,'shortlink_rewards'=>$links];
    $fields=fp_form($r['body'],'/faucet/verify');if(empty($fields['token'])||empty($fields['currency']))throw new RuntimeException('Claim token/currency missing.');
    $started=microtime(true);$key=fp_sitekey($r['body'],'adslab-captcha');
    if($key){fp_log('INFO','AdsLab automatic selection...',$a['label']);$fields['captcha']='alcaptcha';$fields['alcaptcha-response']=fp_adslab($key,$a['label']);}
    elseif($key=fp_sitekey($r['body'],'h-captcha')){$fields['captcha']='hcaptcha';$fields['h-captcha-response']=fp_token(captcha($r['url'],$key,'hcaptcha'));$fields['g-recaptcha-response']=$fields['h-captcha-response'];}
    elseif(isset($fields['rscaptcha_token'])||str_contains($r['body'],'generated_captcha/')) {
        fp_log('INFO','RS captcha...',$a['label']);$rs=rs_upsidedown($r['body'],$r['url']);
        if(empty($rs['rs-response']))throw new RuntimeException('RS captcha solver returned no response.');
        $fields['captcha']='rscaptcha';$fields['rscaptcha_response']=$rs['rs-response'];
    }
    elseif(str_contains($r['body'],'id="selectCaptcha"'))throw new RuntimeException('Unknown captcha widget.');
    if(preg_match('/(?:let|var|const)\s+timer\s*=\s*(\d+)\s*;/',$r['body'],$m)){$wait=(int)$m[1]-(int)(microtime(true)-$started);if($wait>300)throw new RuntimeException('Long claim timer.');if($wait>0)sleep($wait);}
    $file=fp_identity_file($a);$identity=is_file($file)?json_decode(file_get_contents($file),true):fp_identity($a);
    if(is_array($identity)&&($identity['_ua']??'')!==fp_ua()){
        $identity=fp_identity($a);file_put_contents($file,json_encode($identity+['_ua'=>fp_ua()]),LOCK_EX);
    }
    unset($identity['_ua']);
    if(!is_array($identity)||empty($identity['uf']))throw new RuntimeException('Account identity missing.');
    $fields=array_replace($fields,$identity);if(isset($fields['bh_dwell']))$fields['bh_dwell']=(string)(int)((microtime(true)-$started)*1000);
    $r=fp_request('/faucet/verify',$a,$fields);
    if(str_starts_with(parse_url($r['url'],PHP_URL_PATH),'/links/')) {
        fp_log('WAIT','Claim requires a shortlink; completing it, then fetching a new claim form.',$a['label']);
        $links[]=fp_shortlink($a,$coin);
        return ['wait'=>1,'reward'=>null,'shortlink_rewards'=>$links];
    }
    $reward=fp_reward($r['body']);
    if(!$reward)throw new RuntimeException('Claim not confirmed: '.(fp_message($r['body'])?:'no reward message'));
    fp_confirm($reward,$a,'Faucet');return ['wait'=>max(1,fp_wait($r['body'])),'reward'=>$reward,'shortlink_rewards'=>$links];
}
function fp_confirm($reward,$a,$kind) {
    if(stripos((string)$reward['amount'],'e')!==false)$reward['amount']=number_format((float)$reward['amount'],12,'.','');
    $unit=$reward['points_label']??fp_points_label();$extra=$reward['currency']==='coins'?'':' | +'.$reward['points'].' '.$unit;
    fp_log('OK',$kind.' +'.$reward['amount'].' '.strtoupper($reward['currency']).$extra,$a['label']);
    fp_card($kind.' reward confirmed',[$a['label'].'  |  '.fp_site()['title'],'+'.$reward['amount'].' '.strtoupper($reward['currency']).$extra],GREEN);
}
function fp_public_url($url) {
    $host=parse_url($url,PHP_URL_HOST);
    return is_string($host)&&str_contains($host,'.')&&!filter_var($host,FILTER_VALIDATE_IP)&&!preg_match('/(?:^|\.)(localhost|local|internal)$/i',$host)
        &&in_array(parse_url($url,PHP_URL_SCHEME),['http','https'],true)&&!parse_url($url,PHP_URL_USER)&&!parse_url($url,PHP_URL_PASS);
}
function fp_return_path($url) {
    if(parse_url($url,PHP_URL_SCHEME)!=='https'||parse_url($url,PHP_URL_HOST)!==fp_host()||parse_url($url,PHP_URL_USER)||parse_url($url,PHP_URL_PASS))throw new RuntimeException('Shortlink solver returned an unexpected site.');
    $path=parse_url($url,PHP_URL_PATH);
    if(!preg_match('~^/links/(back|verify)/[a-zA-Z0-9_-]+/?$~',$path))throw new RuntimeException('Shortlink solver did not return a reward callback.');
    return $path.(parse_url($url,PHP_URL_QUERY)?'?'.parse_url($url,PHP_URL_QUERY):'');
}
function fp_providers($html,$coin) {
    $xp=fp_xpath($html);$items=[];
    foreach($xp->query('//a[@href]') as $link) {
        $url=html_entity_decode($link->getAttribute('href'),ENT_QUOTES);
        if(str_starts_with($url,'/'))$url=fp_origin().$url;
        if(parse_url($url,PHP_URL_HOST)!==fp_host()||!preg_match('~^/links/go/\d+/'.preg_quote($coin,'~').'/?$~',parse_url($url,PHP_URL_PATH)??''))continue;
        if($link->hasAttribute('disabled')||str_contains($link->getAttribute('class'),'disabled'))continue;
        $node=$link->parentNode;$name='';$text='';
        while($node instanceof DOMElement) {
            $headings=$node->getElementsByTagName('h5');
            if($headings->length===1){$name=trim($headings->item(0)->textContent);$text=$node->textContent;break;}
            $node=$node->parentNode;
        }
        $normalized=strtolower(preg_replace('/[^a-z]/i','',$name));
        $provider=null;foreach(['adlink','shrinkme','shrinkearn','clksh'] as $allowed)if(str_contains($normalized,$allowed)){$provider=$allowed;break;}
        if(!$provider)continue;
        if(preg_match('~\b0\s*/\s*\d+\b~',$text))continue;
        $items[]=['name'=>$name,'provider'=>$provider,'path'=>parse_url($url,PHP_URL_PATH)];
    }
    usort($items,static fn($a,$b)=>array_search($a['provider'],['adlink','shrinkme','shrinkearn','clksh'])<=>array_search($b['provider'],['adlink','shrinkme','shrinkearn','clksh']));
    return $items;
}
function fp_shortlink($a,$coin=null) {
    $coin=$coin??fp_coin($a);$page=fp_request('/links/'.$coin,$a);
    if(!str_starts_with(parse_url($page['url'],PHP_URL_PATH),'/links/')){fp_login($a);$page=fp_request('/links/'.$coin,$a);}
    $items=fp_providers($page['body'],$coin);
    if(!empty($GLOBALS['fp_provider']))$items=array_values(array_filter($items,static fn($item)=>$item['provider']===$GLOBALS['fp_provider']));
    if(!$items)throw new RuntimeException('No available Adlink, Shrinkme, Shrinkearn or Clksh links; account deferred.');
    $errors=[];
    foreach(array_slice($items,0,4) as $item) {
        try {
            fp_log('INFO','Shortlink: '.$item['name'],$a['label']);$r=fp_request($item['path'],$a);
            $fields=fp_form($r['body'],$item['path']);if(empty($fields['token']))throw new RuntimeException('Shortlink form token missing.');
            $key=fp_sitekey($r['body'],'h-captcha');
            if($key){$token=fp_token(captcha(fp_origin().$item['path'],$key,'hcaptcha'));$fields['captcha']='hcaptcha';$fields['g-recaptcha-response']=$token;$fields['h-captcha-response']=$token;}
            elseif($key=fp_sitekey($r['body'],'adslab-captcha')){$fields['captcha']='alcaptcha';$fields['alcaptcha-response']=fp_adslab($key,$a['label']);}
            elseif(str_contains($r['body'],'id="selectCaptcha"'))throw new RuntimeException('Unsupported shortlink captcha.');
            $started=time();$r=fp_request($item['path'],$a,$fields,true);$external=$r['external_url']??'';
            if(!$external)throw new RuntimeException('Shortlink did not provide an external redirect.');
            fp_log('INFO','Vernuable backend solving '.$item['name'].'...',$a['label']);
            $result=Bypass($external);$return=fp_token($result);$path=fp_return_path($return);
            $minimum=fp_site()['shortlink_minimum']??45;$remaining=$minimum-(time()-$started);
            if($remaining>0){fp_log('WAIT','Reward timer '.$remaining.'s',$a['label']);sleep($remaining);}
            // No provider fallback after a reward callback: its outcome might be unknown.
        }catch(Throwable $e){$errors[]=$item['name'].': '.$e->getMessage();fp_log('ERROR',end($errors),$a['label']);continue;}
        $r=fp_request($path,$a);$reward=fp_reward($r['body']);
        if(!$reward)throw new RuntimeException('Shortlink return not confirmed: '.(fp_message($r['body'])?:'no reward message; callback was not repeated'));
        fp_confirm($reward,$a,'Shortlink');return $reward;
    }
    throw new RuntimeException('All available supported providers failed: '.implode(' | ',$errors));
}
function fp_ourcoin_login($a) {
    if(empty($a['password']))throw new RuntimeException('OurCoinCash requires an account password.');
    $r=fp_request('/login',$a);if(str_contains($r['url'],'/dashboard'))return;
    $fields=fp_form($r['body'],'/auth/login');$key=fp_sitekey($r['body'],'adslab-captcha');
    if(!$key)throw new RuntimeException('OurCoinCash login captcha missing.');
    fp_log('INFO','Login AdsLab automatic selection...',$a['label']);
    $fields=array_replace($fields,['email'=>$a['email'],'password'=>$a['password'],'captcha'=>'adslabpro','alcaptcha-response'=>fp_adslab($key,$a['label'])]);
    $r=fp_request('/auth/login',$a,$fields);
    if(!str_contains($r['url'],'/dashboard'))throw new RuntimeException('OurCoinCash login not confirmed.');
    fp_log('OK','OurCoinCash login confirmed.',$a['label']);
}
function fp_ourcoin_claim($a) {
    $r=fp_request('/faucet',$a);
    if(parse_url($r['url'],PHP_URL_PATH)!=='/faucet'){fp_ourcoin_login($a);$r=fp_request('/faucet',$a);}
    if($wait=fp_wait($r['body']))return ['wait'=>$wait,'reward'=>null];
    $fields=fp_form($r['body'],'/faucet/verify');
    if(empty($fields['csrf_token_name'])||empty($fields['token']))throw new RuntimeException('OurCoinCash claim tokens missing.');
    $started=time();$key=fp_sitekey($r['body'],'adslab-captcha');
    if(!$key)throw new RuntimeException('OurCoinCash faucet captcha missing.');
    fp_log('INFO','AdsLab automatic selection...',$a['label']);
    $fields['captcha']='adslabpro';$fields['alcaptcha-response']=fp_adslab($key,$a['label']);
    if(isset($fields['antibotlinks'])){
        fp_log('INFO','Anti-bot challenge...',$a['label']);$order=antibot($r['body']);
        if(!is_string($order)||!preg_match('/^\s*\d+(?:\s+\d+){2,3}\s*$/',$order))throw new RuntimeException('Anti-bot solver returned no valid order.');
        $fields['antibotlinks']=$order;
    }
    if(preg_match('/(?:let|var|const)\s+timer\s*=\s*(\d+)\s*;/',$r['body'],$m)){
        if((int)$m[1]>300)throw new RuntimeException('Long claim timer.');$wait=(int)$m[1]-(time()-$started);if($wait>0)sleep($wait);
    }
    $r=fp_request('/faucet/verify',$a,$fields);
    if(!preg_match('/^([\d,.]+)\s+coins\s+has been added to your balance[.!]?$/i',trim(fp_message($r['body'])),$m))throw new RuntimeException('OurCoinCash claim not confirmed: '.(fp_message($r['body'])?:'no reward message'));
    $amount=str_replace(',','',$m[1]);if(!is_numeric($amount)||(float)$amount<=0)throw new RuntimeException('Invalid coin reward.');
    $reward=['amount'=>$amount,'currency'=>'coins','points'=>0];fp_confirm($reward,$a,'Faucet');
    return ['wait'=>max(1,fp_wait($r['body'])),'reward'=>$reward];
}
function fp_json($path,$a,$fields=null) {
    $r=fp_request($path,$a,$fields,false,true);$data=json_decode($r['body'],true);
    if(!is_array($data))throw new RuntimeException('Invalid JSON response at '.$path);return $data;
}
function fp_lime_login($a) {
    $me=fp_json('/api/auth/me',$a);if(!empty($me['user']))return;
    $result=fp_json('/api/auth/login',$a,['email'=>$a['email'],'referral_code'=>null]);
    if(empty($result['user']['id']))throw new RuntimeException('LimeFaucet login not confirmed.');
    fp_log('OK','LimeFaucet login confirmed.',$a['label']);
}
function fp_lime_claim($a) {
    fp_lime_login($a);$info=fp_json('/api/faucet/info',$a);
    if(($info['time_remaining_seconds']??0)>0)return ['wait'=>(int)$info['time_remaining_seconds'],'reward'=>null];
    $challenge=fp_json('/api/faucet/rotation-captcha/challenge',$a,[]);
    $session=$challenge['session_id']??'';$image=$challenge['challenge']['image']??'';$crop=$challenge['challenge']['crop']??[];
    if(!$session||!is_string($image)||!isset($crop['x'],$crop['y'],$crop['radius']))throw new RuntimeException('LimeFaucet rotation challenge data missing.');
    if(str_starts_with($image,'/'))$path=$image;else {
        if(parse_url($image,PHP_URL_SCHEME)!=='https'||parse_url($image,PHP_URL_HOST)!==fp_host())throw new RuntimeException('Unexpected captcha image host.');
        $path=parse_url($image,PHP_URL_PATH).(parse_url($image,PHP_URL_QUERY)?'?'.parse_url($image,PHP_URL_QUERY):'');
    }
    $base=fp_request('/captcha-lime.jpg',$a)['body'];$piece=fp_request($path,$a)['body'];
    fp_log('INFO','Rotation captcha...',$a['label']);
    $solve=rotation(base64_encode($base),base64_encode($piece),implode(',',[$crop['x'],$crop['y'],$crop['radius']]));
    $angle=$solve['angle']??$solve['request']??null;
    if((int)($solve['status']??0)!==1||!is_numeric($angle)||(float)$angle<0||(float)$angle>360)throw new RuntimeException('LimeFaucet solver returned no valid angle.');
    $verify=fp_json('/api/faucet/rotation-captcha/verify',$a,['session_id'=>$session,'angle'=>(int)$angle]);
    if(($verify['ok']??false)!==true||empty($verify['token']))throw new RuntimeException('LimeFaucet captcha was not verified.');
    $result=fp_json('/api/faucet/claim',$a,['captcha_token'=>$verify['token']]);
    if(!isset($result['reward_crypto'])||!is_numeric($result['reward_crypto'])||(float)$result['reward_crypto']<=0||empty($result['currency']))throw new RuntimeException('LimeFaucet claim not confirmed.');
    $reward=['amount'=>(string)$result['reward_crypto'],'currency'=>strtolower($result['currency']),'points'=>(int)($result['points']??0)];
    fp_confirm($reward,$a,'Faucet');return ['wait'=>max(1,(int)($result['time_remaining_seconds']??1)),'reward'=>$reward];
}
function fp_run($accounts,$loop=true,$mode='faucet') {
    if(!$accounts){fp_log('INFO','Add an account first.');return;}$next=[];$failures=0;$stopped=false;$claims=0;$points=0;$totals=[];$shortlinks=0;
    $selected=$GLOBALS['fp_currency']??'ltc';
    $coins=$selected==='all'?(fp_site()['coins']??['ltc']):[$selected];
    $states=[];foreach($accounts as $a)foreach($coins as $coin){$a['currency']=$coin;$states[]=$a;}
    do {
        $active=false;
        foreach($states as $i=>$a) {
            $active=true;if(($next[$i]??0)>time())continue;
            $lock=fopen(fp_identity_file($a).'.lock','c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB)){if($lock)fclose($lock);$next[$i]=time()+10;continue;}
            try {
                $r=$mode==='shortlinks'?['wait'=>1,'reward'=>null,'shortlink_rewards'=>[fp_shortlink($a)]]:fp_claim($a);
                $failures=0;
                $next[$i]=time()+$r['wait'];
                $rewards=$r['shortlink_rewards']??[];$shortlinks+=count($rewards);
                if($r['reward']){$claims++;$rewards[]=$r['reward'];}
                elseif(!$rewards)fp_log('WAIT','Cooldown '.$r['wait'].'s',$a['label']);
                foreach($rewards as $reward){$points+=$reward['points'];$currency=$reward['currency'];$totals[$currency]=($totals[$currency]??0)+(float)$reward['amount'];}
            }
            catch(Throwable $e){
                $failures++;$next[$i]=time()+5;
                fp_log('ERROR',$e->getMessage().' | Consecutive failures '.$failures.'/3',$a['label']);
                if($failures>=3)$stopped=true;
            }
            finally{flock($lock,LOCK_UN);fclose($lock);}
            if($stopped)break;
        }if(!$loop||!$active)break;sleep(1);
    }while(!$stopped);
    $rows=[(fp_site()['mode']??'form')==='ourcoin'?"$claims faucet claims":"$claims faucet claims  |  $shortlinks shortlinks  |  $points ".fp_points_label()];
    foreach($totals as $coin=>$total)$rows[]=number_format($total,$coin==='coins'?0:8,'.','').' '.strtoupper($coin);
    if(!$totals)$rows[]='No server-confirmed rewards in this run.';
    fp_card('Session results',$rows);
    if($stopped){fp_log('ERROR','Stopped: three consecutive failures.');exit(1);}
}
function fp_input($prompt){echo $prompt;$line=fgets(STDIN);return $line===false?'0':trim($line);}
function fp_main($args,$site=null) {
    if($site!==null)$GLOBALS['fp_site_config']=$site;
    date_default_timezone_set('Asia/Karachi');$root=fp_root();if(!defined('BASE_DIR'))define('BASE_DIR',$root);if(!defined('APP_HOST'))define('APP_HOST','buxads-Bot');
    require_once $root.'/functions/function.php';$private=fp_private();if(!is_dir($private))mkdir($private,0700,true);
    if(!defined('anticaptcha_key'))define('anticaptcha_key',is_file($private.'/vernuable.key')?trim(file_get_contents($private.'/vernuable.key')):trim(saveData(APP_HOST,'anticaptcha-apikey')));
    require_once $root.'/functions/captcha.php';$accounts=fp_accounts();
    if(!extension_loaded('dom'))throw new RuntimeException('PHP DOM extension is required.');
    $GLOBALS['fp_currency']=fp_site()['coins'][0]??'ltc';$GLOBALS['fp_provider']=null;
    for($i=1;$i<count($args);$i++) {
        if($args[$i]==='--currency'){$coin=$args[++$i]??'';if($coin!=='all'&&!in_array($coin,fp_site()['coins']??['ltc'],true))throw new RuntimeException('Unsupported coin.');$GLOBALS['fp_currency']=$coin;}
        if($args[$i]==='--provider'){$provider=strtolower($args[++$i]??'');if(!in_array($provider,['adlink','shrinkme','shrinkearn','clksh'],true))throw new RuntimeException('Unsupported provider.');$GLOBALS['fp_provider']=$provider;}
    }
    if(in_array('--shortlinks-once',$args,true)){if((fp_site()['mode']??'form')!=='form')throw new RuntimeException('This adapter has no shortlink flow.');fp_run($accounts,false,'shortlinks');return;}
    if(in_array('--check',$args,true)) {
        fp_banner($accounts);
        if(!$accounts){fp_log('INFO','No configured accounts.');return;}
        foreach($accounts as $a)try{fp_login($a);fp_log('OK','Login/session checked.',$a['label']);}catch(Throwable $e){fp_log('ERROR',$e->getMessage(),$a['label']);}
        return;
    }
    if(in_array('--once',$args,true)||in_array('--loop',$args,true)){fp_run($accounts,in_array('--loop',$args,true));return;}
    if(in_array('--login-test',$args,true)){if(!$accounts)throw new RuntimeException('No accounts.');$a=$accounts[0];$a['session_prefix']='fp-test-';fp_login($a,true);return;}
    while(true) {
        fp_banner($accounts);
        echo WHITE."  1  Run account          2  Run all (Smart)\n  3  Add account          4  View accounts\n  5  Delete account       6  API balance\n  7  Run all once         8  Shortlinks once\n  9  Select coin          0  Return\n\n";
        $choice=fp_input('Choose: ');$accounts=fp_accounts();
        try {
            if($choice==='0')return;
            elseif($choice==='1'){foreach($accounts as $i=>$a)echo ($i+1).'. '.$a['email'].PHP_EOL;$n=(int)fp_input('Account: ')-1;if(!isset($accounts[$n]))throw new RuntimeException('Invalid account.');fp_run([$accounts[$n]]);}
            elseif($choice==='2')fp_run($accounts);
            elseif($choice==='3'){
                $ourcoin=(fp_site()['mode']??'form')==='ourcoin';$email=fp_input($ourcoin?'Account email: ':'FaucetPay email: ');
                if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Invalid email.');
                foreach($accounts as $a)if(strcasecmp($a['email'],$email)===0)throw new RuntimeException('Account exists.');
                $password=$ourcoin?fp_input('Password: '):'';
                if($ourcoin&&($password===''||str_contains($password,'|')))throw new RuntimeException('A password without the account-file delimiter is required.');
                $proxy=fp_input('Proxy (Enter for none): ');if(str_contains($proxy,'|'))throw new RuntimeException('Invalid proxy.');
                $accounts[]=['email'=>$email,'password'=>$password,'proxy'=>$proxy];fp_save_accounts($accounts);fp_log('OK','Account saved.');
            }
            elseif($choice==='4')foreach($accounts as $i=>$a)echo ($i+1).'. '.$a['email'].' | '.($a['proxy']?'proxy':'direct').PHP_EOL;
            elseif($choice==='5'){
                if(!$accounts){fp_log('INFO','No accounts to delete.');continue;}
                foreach($accounts as $i=>$a)echo ($i+1).'. '.$a['email'].PHP_EOL;
                $n=(int)fp_input('Delete account number (0 cancels): ')-1;
                if($n===-1)continue;if(!isset($accounts[$n]))throw new RuntimeException('Invalid account.');
                unset($accounts[$n]);fp_save_accounts($accounts);fp_log('OK','Account removed from saved accounts.');
            }
            elseif($choice==='6')fp_log('INFO','API balance: '.(get_balance()??'unavailable'));
            elseif($choice==='7')fp_run($accounts,false);
            elseif($choice==='8'){if((fp_site()['mode']??'form')!=='form')throw new RuntimeException('Shortlinks are not available in this adapter.');fp_run($accounts,false,'shortlinks');}
            elseif($choice==='9'){$coin=strtolower(fp_input('Coin ('.implode('/',fp_site()['coins']??['ltc']).'/all): '));if($coin!=='all'&&!in_array($coin,fp_site()['coins']??['ltc'],true))throw new RuntimeException('Unsupported coin.');$GLOBALS['fp_currency']=$coin;}
            else fp_log('ERROR','Invalid option.');
        }catch(Throwable $e){fp_log('ERROR',$e->getMessage());}
    }
}
}

/** FaucetPepe instant bot. @version 1.2.0 */
if (!defined('FP_LIBRARY_ONLY')) {
    try {
        fp_main($_SERVER['argv'] ?? [], ['host'=>'faucetpepe.xyz', 'title'=>'FaucetPepe', 'mode'=>'form', 'coins'=>['ltc']]);
    } catch (Throwable $e) { echo 'ERROR: ' . $e->getMessage() . PHP_EOL; }
}
