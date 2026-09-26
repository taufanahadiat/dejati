<?php
// Run on the host: php tests/android_backend_order_type.php
// Uses a disposable copy of the standalone Android API and fixture data only.
if (PHP_SAPI !== 'cli') exit;
$root = sys_get_temp_dir().'/android-type-'.bin2hex(random_bytes(5));
$source = realpath(__DIR__.'/../../backend-api/public');
$process = null;
function expectAndroid($ok, $message) { if (!$ok) throw new RuntimeException($message); }
try {
    mkdir($root.'/public',0700,true); mkdir($root.'/data',0700,true);
    foreach (['index.php','router.php','order-type.php'] as $file) copy($source.'/'.$file,$root.'/public/'.$file);
    file_put_contents($root.'/data/seed.json',json_encode(['users'=>[['id'=>1,'username'=>'fixture','password'=>'fixture','name'=>'Test','role'=>'Kasir','status'=>'Aktif']], 'orders'=>[]]));
    $socket=stream_socket_server('tcp://127.0.0.1:0'); $address=stream_socket_get_name($socket,false); fclose($socket);
    $process=proc_open([PHP_BINARY,'-S',$address,'-t',$root.'/public',$root.'/public/router.php'],[0=>['pipe','r'],1=>['file',$root.'/log','a'],2=>['file',$root.'/log','a']],$pipes);
    for($i=0;$i<50;$i++){ $s=@stream_socket_client('tcp://'.$address); if($s){fclose($s);break;} usleep(20000); }
    $token='';
    $request=static function($path,$body=null,$status=200) use($address,&$token) {
        $c=stream_context_create(['http'=>['method'=>$body===null?'GET':'POST','header'=>"Content-Type: application/json\r\nAuthorization: Bearer $token",'content'=>$body===null?'':json_encode($body),'ignore_errors'=>true,'timeout'=>10]]);
        $response=file_get_contents('http://'.$address.'/'.$path,false,$c);
        expectAndroid(str_contains($http_response_header[0],(string)$status),$path.' '.$http_response_header[0].' '.$response);
        return json_decode($response,true);
    };
    $token=$request('auth/login',['username'=>'fixture','password'=>'fixture'])['token'];
    $payload=['tableNumber'=>'TEST','paymentMethod'=>'cash','paidAmount'=>30000,'items'=>[
        ['productId'=>1,'name'=>'Coffee','qty'=>1,'unitPrice'=>10000,'type'=>'product','orderType'=>'dine-in'],
        ['productId'=>1,'name'=>'Coffee','qty'=>1,'unitPrice'=>10000,'type'=>'product','orderType'=>'take-away'],
        ['productId'=>1,'name'=>'Legacy','qty'=>1,'unitPrice'=>10000,'type'=>'product']
    ]];
    $created=$request('transactions',$payload,201)['transaction'];
    expectAndroid(array_column($created['items'],'orderType')===['dine-in','take-away',null],'POST retains order types');
    $read=$request('transactions/'.$created['id'])['transaction'];
    expectAndroid(array_column($read['items'],'orderType')===['dine-in','take-away',null],'GET retains types');
    $payload['items'][0]['orderType']='bad'; $request('transactions',$payload,422);
    expectAndroid(count($request('transactions')['transactions'])===1,'Invalid payload not persisted');
    echo "PASS: standalone Android API writes, reads and validates item order type\n";
} finally {
    if(is_resource($process)){proc_terminate($process);proc_close($process);}
    if(is_dir($root)){
        $files=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
        foreach($files as $f) $f->isDir()?rmdir($f->getPathname()):unlink($f->getPathname()); rmdir($root);
    }
}
