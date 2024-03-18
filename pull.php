<?php
 function checksum($subpath)
 {
  $checksumBase = 317426846;
  $base = $checksumBase;
  for ($i = 0; $i < strlen($subpath); $i++)
  {
   $base = ($base << 5) - $base + ord(substr($subpath, $i, 1));
   $base &= 0xFFFFFFFF;
  }
  return base_convert(($base & 255), 10, 16);
 }
 function fetch($url, $file)
 {
  $ret = @file_get_contents($url);
  if ($ret === false)
   return false;
  return file_put_contents($file, $ret) !== false;
 }
 header('content-type: text/plain');
 $v = 'latest';
 if (array_key_exists('v', $_GET))
  $v = preg_replace('/[^0-9\.]/', '', $_GET['v']);
 $stats = array(
  'unqualified' => -1,
  'minimally-qualified' => 0,
  'fully-qualified' => 1
 );
 $safeSkip = $v;
 $data = file_get_contents('https://unicode.org/Public/emoji/'.$v.'/emoji-test.txt');
 $lns = explode("\n", $data);
 $db = array();
 for ($i = 0; $i < count($lns); $i++)
 {
  if (empty($lns[$i]))
   continue;
  if (substr($lns[$i], 0, 1) === '#')
  {
   if (strlen($lns[$i]) > 11 && substr($lns[$i], 0, 11) === '# Version: ')
    $safeSkip = trim(substr($lns[$i], 11));
   continue;
  }
  if (!str_contains($lns[$i], ';'))
   continue;
  if (!str_contains($lns[$i], '#'))
   continue;
  $code = explode(' ', trim(substr($lns[$i], 0, strpos($lns[$i], ';'))));
  $status = substr($lns[$i], strpos($lns[$i], ';') + 1);
  $status = trim(substr($status, 0, strpos($status, '#')));
  $details = explode(' ', trim(substr($lns[$i], strpos($lns[$i], '#') + 1)), 3);
  if (count($details) !== 3)
   continue;
  $value = $details[0];
  $version = $details[1];
  if (substr($version, 0, 1) !== 'E')
   continue;
  $version = substr($version, 1);
  $name = $details[2];
  $codeStd = strtolower(implode('_', $code));
  if (!array_key_exists($status, $stats))
   continue;
  if ($stats[$status] < 1)
   $db[$codeStd] = array('target' => $name, 'status' => $status);
  else
   $db[$codeStd] = array('name' => $name, 'ver' => $version);
 }
 foreach ($db as $k => $v)
 {
  if (!array_key_exists('target', $v))
   continue;
  $found = false;
  foreach ($db as $k2 => $v2)
  {
   if (!array_key_exists('name', $v2))
    continue;
   if ($v2['name'] !== $v['target'])
    continue;
   $db[$k]['target'] = $k2;
   if (!array_key_exists('aliases', $v2))
    $db[$k2]['aliases'] = array();
   $db[$k2]['aliases'][] = strval($k);
   $found = true;
   break;
  }
  if (!$found)
   unset($db[$k]);
 }

 $skipped = array();
 $ct = count(array_keys($db));
 $iIDX = 0;
 foreach ($db as $k => $v)
 {
  $iIDX++;
  if (!array_key_exists('name', $v))
   continue;
  $code = explode('-', $k);
  $name = $v['name'];
  $codeStd = strtolower(implode('_', $code));
  $codeNoZ = ltrim($codeStd, '0');
  $fList = array($codeNoZ);

  $pct = floor(($iIDX/$ct) * 100);

  if (array_key_exists('aliases', $v))
  {
   foreach ($v['aliases'] as $alias)
    $fList[] = ltrim(strtolower(implode('_', explode('-', $alias))), '0');
  }

  $found = false;
  $fiRo = true;
  foreach ($fList as $fTest)
  {
   $p = '3/128/'.$fTest.'.png';
   if ($fiRo)
   {
    $fiRo = false;
    echo "[$pct%] Downloading $name...";
   }
   $u = 'https://static.xx.fbcdn.net/images/emoji.php/v9/t'.checksum($p).'/'.$p;
   if (fetch($u, 'png/'.$fTest.'.png'))
   {
    echo " OK!\n";
    $found = true;
    break;
   }
  }
  if (!$found)
  {
   echo " Not Found";
   if ($v['ver'] !== $safeSkip)
    die("\n");
   echo " - Skipping Latest (v$safeSkip)\n";
  }
 }
 echo "Complete!\n";
?>