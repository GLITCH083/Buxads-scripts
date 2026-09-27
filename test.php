<?php
$apiKey = '8d4a-4fcf-4261-4049-8378';
function vn_request($url, $fields, $jsonBody = false) {
  $ch = curl_init($url);
  $headers = ['Accept: application/json', 'User-Agent: Mozilla/5.0 VernuablePHP/1.0'];
  if ($jsonBody) {
    $headers[] = 'Content-Type: application/json';
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
  } else {
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
  }
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_TIMEOUT => 180,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,
  ]);
  $raw = curl_exec($ch);
  $err = curl_error($ch);
  curl_close($ch);
  if ($raw === false || $raw === '') throw new Exception('HTTP fail: ' . $err);
  $json = json_decode($raw, true);
  if (!is_array($json)) throw new Exception('Bad JSON: ' . substr($raw, 0, 200));
  return $json;
}
try {
  $task = vn_request('https://vernuable.my.id/in.php', [
    'key' => $apiKey,
    'method' => 'hcaptcha',
    'sitekey' => 'a5f74b19-9e45-40e0-b45d-47ff91b7a6c2',
    'pageurl' => 'https://accounts.hcaptcha.com/demo',
    'json' => '1',
  ], true);
  if (($task['status'] ?? 0) != 1) exit('Submit failed: ' . json_encode($task) . PHP_EOL);
  $id = $task['request'];
  echo "Task: $id\n";
  while (true) {
    sleep(5);
    $res = vn_request('https://vernuable.my.id/res.php', [
      'key' => $apiKey, 'action' => 'get', 'id' => $id, 'json' => '1',
    ], false);
    if (($res['status'] ?? 0) == 1) { echo $res['request'] . PHP_EOL; break; }
    if (($res['request'] ?? '') === 'CAPCHA_NOT_READY') continue;
    exit('Error: ' . json_encode($res) . PHP_EOL);
  }
} catch (Exception $e) {
  echo 'Exception: ' . $e->getMessage() . PHP_EOL;
}