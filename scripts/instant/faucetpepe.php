<?php
/** FaucetPepe - BuxAds shared functions. @version 1.1.0 */
if (!function_exists('fp_main')) {
function fp_root() {
    $dir=__DIR__;
    for($i=0;$i<5;$i++) {if(is_file($dir.'/functions/function.php'))return $dir;$dir=dirname($dir);}
    throw new RuntimeException('Save this script inside Buxads-scripts.');
}
function fp_log($level,$message,$label='') {
    $color=['OK'=>GREEN,'INFO'=>CYAN,'WAIT'=>YELLOW,'ERROR'=>RED][$level]??WHITE;
    $line=sprintf('[%s] %-5s %-12s %s',date('H:i:s'),$level,$label,preg_replace('/[\x00-\x1f\x7f]/',' ',(string)$message));
    echo $color.$line."\033[0m\n";
    file_put_contents(fp_root().'/configs/faucetpepe-client/events.log',$line.PHP_EOL,FILE_APPEND|LOCK_EX);
}
function fp_accounts_file() {return fp_root().'/information/faucetpepe.xyz.txt';}
function fp_accounts() {
    if(!is_file(fp_accounts_file()))return [];$accounts=[];
    foreach(file(fp_accounts_file(),FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) as $line) {
        if(str_starts_with(trim($line),'#'))continue;$p=explode('|',trim($line));
        if(filter_var($p[0]??'',FILTER_VALIDATE_EMAIL))$accounts[]=['email'=>$p[0],'password'=>$p[1]??'','proxy'=>$p[2]??'','label'=>'Account '.(count($accounts)+1)];
    }return $accounts;
}
function fp_save_accounts($accounts) {
    $text="# email|password(optional)|proxy(optional)\n";
    foreach($accounts as $a)$text.=$a['email'].'|'.($a['password']??'').'|'.($a['proxy']??'')."\n";
    if(file_put_contents(fp_accounts_file(),$text,LOCK_EX)===false)throw new RuntimeException('Cannot save accounts.');
    @chmod(fp_accounts_file(),0600);
}
function fp_ua() {return trim(saveData(APP_HOST,'user-agent'));}
function fp_cookie_name($a) {return ($a['session_prefix']??'fp-').md5(strtolower($a['email']));}
function fp_identity_file($a) {return fp_root().'/configs/faucetpepe-client/'.fp_cookie_name($a).'.json';}
function fp_request($path,$a,$fields=null) {
    $url='https://faucetpepe.xyz'.$path;
    for($i=0;$i<6;$i++) {
        $headers=['accept: text/html,application/xhtml+xml,application/json','accept-language: en-US,en;q=0.9','user-agent: '.fp_ua()];
        if($fields!==null)$headers=array_merge($headers,['content-type: application/x-www-form-urlencoded','origin: https://faucetpepe.xyz','referer: https://faucetpepe.xyz/']);
        $r=Run($url,$headers,$fields!==null?http_build_query($fields):null,'data',$a['proxy']?:null,2,fp_cookie_name($a));
        $status=(int)($r['info']['http_code']??0);if(!$status||$status>=400)throw new RuntimeException('HTTP '.$status.' at '.parse_url($url,PHP_URL_PATH));
        $next=$r['info']['redirect_url']??'';
        if($status>=300&&$status<400&&$next) {
            if(str_starts_with($next,'/'))$next='https://faucetpepe.xyz'.$next;
            if(parse_url($next,PHP_URL_HOST)!=='faucetpepe.xyz'||parse_url($next,PHP_URL_SCHEME)!=='https')throw new RuntimeException('External redirect stopped.');
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
    if(preg_match('/Swal\.fire\s*\(\s*\{([\s\S]*?)\}\s*\)/',$html,$b)&&preg_match('/\btext\s*:\s*([\x27\x22])((?:\\\\.|(?!\1).)*)\1/s',$b[1],$m))return html_entity_decode(stripslashes($m[2]),ENT_QUOTES|ENT_HTML5);
    return '';
}
function fp_reward($html) {
    if(!preg_match('/([\d.]+)\s+([a-z]+)\s*&\s*(\d+)\s+PTS has been sent to your account/i',fp_message($html),$m))return null;
    return ['amount'=>$m[1],'currency'=>strtolower($m[2]),'points'=>(int)$m[3]];
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
        try{return fp_token(solve_adslab($key,'faucetpepe.xyz'));}
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
    $r=fp_request('/',$a);if(!$force&&str_contains($r['url'],'/dashboard'))return;
    $fields=fp_form($r['body'],'/auth/login');$key=fp_sitekey($r['body'],'h-captcha');if(!$key)throw new RuntimeException('hCaptcha sitekey missing.');
    $identity=fp_identity($a);fp_log('INFO','Login captcha...',$a['label']);
    $token=fp_token(captcha('https://faucetpepe.xyz/',$key,'hcaptcha'));
    $fields=array_replace($fields,$identity,['email'=>$a['email'],'captcha'=>'hcaptcha','g-recaptcha-response'=>$token,'h-captcha-response'=>$token]);
    $r=fp_request('/auth/login',$a,$fields);if(!str_contains($r['url'],'/dashboard'))throw new RuntimeException('Login not confirmed: '.(fp_message($r['body'])?:'dashboard missing'));
    file_put_contents(fp_identity_file($a),json_encode($identity),LOCK_EX);fp_log('OK','MD5 login confirmed.',$a['label']);
}
function fp_claim($a) {
    $r=fp_request('/faucet/ltc',$a);if(!str_contains($r['url'],'/faucet/')){fp_login($a);$r=fp_request('/faucet/ltc',$a);}
    if($wait=fp_wait($r['body']))return ['wait'=>$wait,'reward'=>null];
    $fields=fp_form($r['body'],'/faucet/verify');if(empty($fields['token'])||empty($fields['currency']))throw new RuntimeException('Claim token/currency missing.');
    $started=microtime(true);$key=fp_sitekey($r['body'],'adslab-captcha');
    if($key){fp_log('INFO','AdsLab automatic selection...',$a['label']);$fields['captcha']='alcaptcha';$fields['alcaptcha-response']=fp_adslab($key,$a['label']);}
    elseif($key=fp_sitekey($r['body'],'h-captcha')){$fields['captcha']='hcaptcha';$fields['h-captcha-response']=fp_token(captcha($r['url'],$key,'hcaptcha'));$fields['g-recaptcha-response']=$fields['h-captcha-response'];}
    elseif(str_contains($r['body'],'id="selectCaptcha"'))throw new RuntimeException('Unknown captcha widget.');
    if(preg_match('/(?:let|var|const)\s+timer\s*=\s*(\d+)\s*;/',$r['body'],$m)){$wait=(int)$m[1]-(int)(microtime(true)-$started);if($wait>300)throw new RuntimeException('Long claim timer.');if($wait>0)sleep($wait);}
    $file=fp_identity_file($a);$identity=is_file($file)?json_decode(file_get_contents($file),true):fp_identity($a);
    if(!is_array($identity)||empty($identity['uf']))throw new RuntimeException('Account identity missing.');
    $fields=array_replace($fields,$identity);if(isset($fields['bh_dwell']))$fields['bh_dwell']=(string)(int)((microtime(true)-$started)*1000);
    $r=fp_request('/faucet/verify',$a,$fields);$reward=fp_reward($r['body']);if(!$reward)throw new RuntimeException('Claim not confirmed: '.(fp_message($r['body'])?:'no reward message'));
    fp_log('OK','+'.$reward['amount'].' '.strtoupper($reward['currency']).' | +'.$reward['points'].' PTS',$a['label']);return ['wait'=>max(1,fp_wait($r['body'])),'reward'=>$reward];
}
function fp_run($accounts,$loop=true) {
    if(!$accounts){fp_log('INFO','Add an account first.');return;}$next=[];$disabled=[];$claims=0;$points=0;$total=0;
    do {
        $active=false;
        foreach($accounts as $i=>$a) {
            if(isset($disabled[$i]))continue;$active=true;if(($next[$i]??0)>time())continue;
            $lock=fopen(fp_identity_file($a).'.lock','c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB)){if($lock)fclose($lock);$next[$i]=time()+10;continue;}
            try{$r=fp_claim($a);$next[$i]=time()+$r['wait'];if($r['reward']){$claims++;$points+=$r['reward']['points'];$total+=(float)$r['reward']['amount'];}else fp_log('WAIT','Cooldown '.$r['wait'].'s',$a['label']);}
            catch(Throwable $e){fp_log('ERROR',$e->getMessage(),$a['label']);$disabled[$i]=true;}
            finally{flock($lock,LOCK_UN);fclose($lock);}
        }if(!$loop||!$active)break;sleep(1);
    }while(true);fp_log('INFO',"$claims claims | ".number_format($total,8,'.','')." LTC | $points PTS");
}
function fp_input($prompt){echo $prompt;$line=fgets(STDIN);return $line===false?'0':trim($line);}
function fp_main($args) {
    date_default_timezone_set('Asia/Karachi');$root=fp_root();if(!defined('BASE_DIR'))define('BASE_DIR',$root);if(!defined('APP_HOST'))define('APP_HOST','buxads-Bot');
    require_once $root.'/functions/function.php';$private=$root.'/configs/faucetpepe-client';if(!is_dir($private))mkdir($private,0700,true);
    if(!defined('anticaptcha_key'))define('anticaptcha_key',is_file($private.'/vernuable.key')?trim(file_get_contents($private.'/vernuable.key')):trim(saveData(APP_HOST,'anticaptcha-apikey')));
    require_once $root.'/functions/captcha.php';$accounts=fp_accounts();
    if(in_array('--once',$args,true)||in_array('--loop',$args,true)){fp_run($accounts,in_array('--loop',$args,true));return;}
    if(in_array('--login-test',$args,true)){if(!$accounts)throw new RuntimeException('No accounts.');$a=$accounts[0];$a['session_prefix']='fp-test-';fp_login($a,true);return;}
    while(true) {
        echo CYAN."\n+-----------------------------------+\n|      FAUCETPEPE / BUXADS           |\n+-----------------------------------+\n".WHITE."1. Run account\n2. Run all accounts (Smart)\n3. Add account\n4. View accounts\n5. Delete account\n6. API balance\n7. Run all once\n0. Return\n";
        $choice=fp_input('Choose: ');$accounts=fp_accounts();
        try {
            if($choice==='0')return;
            elseif($choice==='1'){foreach($accounts as $i=>$a)echo ($i+1).'. '.$a['email'].PHP_EOL;$n=(int)fp_input('Account: ')-1;if(!isset($accounts[$n]))throw new RuntimeException('Invalid account.');fp_run([$accounts[$n]]);}
            elseif($choice==='2')fp_run($accounts);
            elseif($choice==='3'){$email=fp_input('FaucetPay email: ');if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Invalid email.');foreach($accounts as $a)if(strcasecmp($a['email'],$email)===0)throw new RuntimeException('Account exists.');$proxy=fp_input('Proxy (Enter for none): ');if(str_contains($proxy,'|'))throw new RuntimeException('Invalid proxy.');$accounts[]=['email'=>$email,'password'=>'','proxy'=>$proxy];fp_save_accounts($accounts);fp_log('OK','Account saved.');}
            elseif($choice==='4')foreach($accounts as $i=>$a)echo ($i+1).'. '.$a['email'].' | '.($a['proxy']?'proxy':'direct').PHP_EOL;
            elseif($choice==='5'){$n=(int)fp_input('Account number: ')-1;if(!isset($accounts[$n]))throw new RuntimeException('Invalid account.');unset($accounts[$n]);fp_save_accounts($accounts);fp_log('OK','Account removed.');}
            elseif($choice==='6')fp_log('INFO','API balance: '.(get_balance()??'unavailable'));
            elseif($choice==='7')fp_run($accounts,false);else fp_log('ERROR','Invalid option.');
        }catch(Throwable $e){fp_log('ERROR',$e->getMessage());}
    }
}
}
if(!defined('FP_LIBRARY_ONLY')){
    try{fp_main($_SERVER['argv']??[]);}catch(Throwable $e){echo 'ERROR: '.$e->getMessage().PHP_EOL;if(realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__)exit(1);}
}
