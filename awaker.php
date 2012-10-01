<?php
require_once 'HttpClient.class.php';
/**
 * 
 * Enter description here ...
 * @var unknown_type
 */
$res = HttpClient::quickGet("http://www.awaker.net/");
$res = getelm($res, "post-list", "class", "div");
$text = '';
foreach ($res as $v)
{
	$text .= $v;
}
//echo $text;

preg_match("#<h2><a[^>]*href=\"http://www.awaker.net/\?p=([0-9]*)\"[^>]*>(.*)</a></h2>#iUs", $text, $maxid);
$maxid = $maxid[1];

$mongo = new mongo("127.0.0.1");
$mixid = $mongo->newsurl->awaker->findOne(array("index"=>"awaker"));
$mixid = $mixid['id'];

echo $mixid." - ".$maxid;
for ($i = $mixid+1; $i <= $maxid;)
{
	$res = HttpClient::quickGet("http://www.awaker.net/?p=$i");
	$j = 0;
	while (!$res&&$j<5)
	{
		$j++;
		$res = HttpClient::quickGet("http://www.awaker.net/?p=$i");
	}

	if($j==5&&!$res)
	{
		$mongo->newsurl->awaker->insert(array("id"=>$i, "state"=>"timeout"));
		$i++;
		echo "
		$j>>".$i.">>timeout";
		continue;
	}
	$res = clearelm($res, "authorinfo", "class", "div");
	$res = clearelm($res, "posttag", "class", "div");
	$res = clearelm($res, "navigation", "class", "div");
	$res = clearelm($res, "ckepop", "id", "div");
	$res = getelm($res, "post-list", "class", "div");
	$text = '';
	foreach ($res as $v)
	{
		$text .= $v;
	}
	preg_match("#<p class=\"postinfo\">([^<]*) /#iUs", $text, $pubdate);
	$text = clearelm($text, "postinfo", "class", "p");
	preg_match("#(<h[1-6][^>]*>)(.*)(</h[1-6]>)#iUs", $text, $title);
	if(strripos($title[2],"没有找到页面"))
	{
		$mongo->newsurl->awaker->insert(array("id"=>$i, "state"=>"notfind"));
		$i++;
		echo "
		$j>>".$i.">>notfind";
		continue;
	}

	if(!$title[2])
	{
		$mongo->newsurl->awaker->insert(array("id"=>$i, "state"=>"untitled"));
		$i++;
		echo "
		$j>>".$i.">>notfind";
		continue;
	}
	preg_match_all("#(<p[^>]*>)(.*)(</p>)#iUs", $text, $matches);
	$content = '';
	foreach ($matches[0] as $v)
	{
		$content .= $v;
	}
	$content = preg_replace("#<a[^>]*>|</a>#iUs", "", $content);

	preg_match_all("#<img[^>]*>#iUs", $content, $imgs);
	//$img_arr = array();
	//print_r($imgs);
	//exit();
	$img_arr = array();
	foreach ($imgs[0] as $img)
	{
		preg_match("#<img[^>]*src=\"(.*)\"[^>]*>#iUs", $img, $fimg);
		$content = str_replace($img, "", $content);
		$img_arr[] = array("pic"=>$fimg[1], "alt"=>"");
		//		echo $fimg[1];
	}
	$content = str_replace("苹果设备点击这里观看视频", "", $content);
	//exit();
	$article = array();
	$article['root_url'] = "http://www.awaker.net/";
	$article['p_url'] = "";
	$getid = $mongo->raw_data->command(array('findAndModify' => 'counters', 'query' => array('ns' => "news"), 'update' => array('$inc' => array('next' => 1)), 'new' => TRUE, 'upsert' => TRUE));
	$id = intval($getid['value']['next']);
	$article['id'] = $id;
	$article['title'] = $title[2];
	$article['label'] = array("科技", "科普");
	$article['column'] = "科普";
	$article['pubdate'] = strtotime($pubdate[1]);
	$article['fetchtime'] = time();
	$article['img'] = $img_arr;
	//print_r($img_arr);exit();
	$article['content'] = $content;
	$article['from'] = "觉醒字幕组";
	$article['source_url'] = "http://www.awaker.net/?p=$i";
	$article['channelid'] = 100010;
	$awaker = date("Ymd");
	$mongo->news->$awaker->insert($article);

	$mongo->newsurl->awaker->update(array("index"=>"awaker"), array('$set'=>array("id"=>$i)));

	echo "
	$j>>".$i.">>".$article['title'];
	$i++;

}
function clearelm($text, $val, $prams, $label='')
{
	if($label == "") $label = "[a-z]*?";
	preg_match_all("#<($label)\s*[^>]*$prams\s*=\s*[\'\"]".$val."[\'\"][^>]*?>#iUs", $text, $m);
	foreach ($m[1] as $k => $v)
	{
		$loop = TRUE;
		$preg = $m[0][$k]."(.*)</".$m[1][$k].">";
		$looppreg = $preg;
		$temp = '';
		while ($loop)
		{
			//			sleep(1);
			preg_match("#$looppreg#iUs", $text, $matches);
			$temp = $matches[0];
			preg_match_all("#<".$m[1][$k]."[^>]*>#iUs", $temp, $matches);
			preg_match_all("#</".$m[1][$k].">#iUs", $temp, $endmatches);
			$times = count($matches[0]);
			$endtimes = count($endmatches[0]);
			if($times == $endtimes) break;
			$looppreg = $preg;
			for ($i = 0; $i < $times-1; $i++)
			{
				$looppreg .= "(.*)</".$m[1][$k].">";
			}
		}
		$text = str_replace($temp, "", $text);
	}
	return $text;
}

function getelm($text, $val, $prams, $label='')
{
	if($label == "") $label = "[a-z]*?";
	preg_match_all("#<($label)\s*[^>]*$prams\s*=\s*[\'\"]".$val."[\'\"][^>]*?>#iUs", $text, $m);
	$rv = array();
	foreach ($m[1] as $k => $v)
	{
		$loop = TRUE;
		$preg = $m[0][$k]."(.*)</".$m[1][$k].">";
		$looppreg = $preg;
		$temp = '';
		while ($loop)
		{
			//			sleep(1);
			preg_match("#$looppreg#iUs", $text, $matches);
			$temp = $matches[0];
			preg_match_all("#<".$m[1][$k]."[^>]*>#iUs", $temp, $matches);
			preg_match_all("#</".$m[1][$k].">#iUs", $temp, $endmatches);
			$times = count($matches[0]);
			$endtimes = count($endmatches[0]);
			if($times == $endtimes) break;
			$looppreg = $preg;
			for ($i = 0; $i < $times-1; $i++)
			{
				$looppreg .= "(.*)</".$m[1][$k].">";
			}
		}
		$text = str_replace($temp, "", $text);
		$rv[] = $temp;
	}
	return $rv;
}

?>