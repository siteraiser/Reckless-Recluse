<?php error_reporting(0);
/*	Copyright © 2017 
	//check line 680 (buf decode if there are encoding issues with links)
	This file is part of Reckless Recluse.
    Reckless Recluse is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.
    Reckless Recluse is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.
    You should have received a copy of the GNU General Public License
    along with Reckless Recluse.  If not, see <http://www.gnu.org/licenses/>.
*/?>
<html>
<head></head>
<body>
<form method="get">
Url:<input placeholder="https://www.example.com/" type="text" style="width:250px;" name="name" value="<?php echo@$_GET['name']?>"><br>
Levels:<input placeholder="limited to 3 in demo" type="text" name="levels" value="<?php echo@$_GET['levels']?>"><br>
Curl Timeout:<input placeholder="download timeout" type="text" name="download_timeout" value="<?php echo (@$_GET['download_timeout']?@$_GET['download_timeout']:5);?>"><br>
Use Slash:<input type="checkbox" name="slash" <?php echo (@$_GET['slash']?'checked':'');?> value="1"><br>
Check External:<input type="checkbox" name="external" <?php echo (@$_GET['external']?'checked':'');?> value="1"><br>

  <div>
  <button type="submit">Crawlem!
  </button>
  </div>
</form>



<?php
ini_set('max_execution_time', $maxextime = 1800); //seconds
ini_set('memory_limit','2048M');//2048M4096M
require_once $_SERVER['DOCUMENT_ROOT'].'/'.'vendor/autoload.php';




/* old
use GraphAware\Neo4j\Client\ClientBuilder;
*/
//$this->client = ClientBuilder::create()->addConnection('default', 'http://neo4j:admin@172.76.227.199')->build(); // Example for HTTP connection configuration (port is optional)	


use Laudis\Neo4j\Authentication\Authenticate;
use Laudis\Neo4j\ClientBuilder;



$curl_timeout = @$_GET['download_timeout'];//seconds
//$reqs = array("http://example.com/");
$crawl_level = 0;
 if(@$_GET['name'] !='') {
	$reqs = array("{$_GET['name']}");
 }	else {
	 exit; 
 } 

 if(@$_GET['levels'] !='') {	
	$crawl_level = ($_GET['levels'] ? $_GET['levels'] : 0) ;
 }
 
//https://www.w3.org/TR/xpath/#path-abbrev https://www.w3.org/TR/xpath/#location-paths
$data['title'] = ['head'=>['//title'=>['text']]];
$data['description'] = ['head'=>['//meta[@name="description"]'=>['content']]];
$data['keywords'] = ['head'=>['//meta[contains(attribute::name, "keywords")]'=>['content']]];
$data['h1s'] = ['body'=>['//h1'=>['text']]];
//$data['script'] = ['query'=>['//script[contains(attribute::type, "application/ld+json")]'=>['innertext']]];
$data['a'] = ['body'=>['.//a'=>['href']]];//['h2'=>['.//a'=>['href','title']]];//['main'=>['.//a'=>['href']],'nav'=>['.//a'=>['href']]]; 
//$data['mobile'] = ['head'=>['//link[contains(attribute::rel, "alternate")]'=>['href']]];
//$data['canonical'] = ['head'=>['//link[contains(attribute::rel, "canonical")]'=>['href']]];
//$crawldata['a'] = ['body'=>['.//a'=>['href']]];
//$data['headerlinks'] = ['header'=>['.//a'=>['href']]];
//$data['navlinks'] = ['nav'=>['.//a'=>['href']]];//$data['navlinks'] = ['nav[0]'=>['.//a'=>['href']]]; // or maybe  nav[position()=5]
$data['mainlinks'] = ['main'=>['.//a'=>['href','innertext','rel']]];
//$data['asidelinks'] = ['aside'=>['.//a'=>['href','innertext']]];
//$data['footerlinks'] = ['footer'=>['.//a'=>['href']]];
$data['IFrames'] = ['body'=>['.//iframe'=>['src']]];
//$data['name'] = ['address[contains(attribute::class, "fullAddress")]'=>['strong[@itemprop="name"]'=>['text']]];
//$data['Address'] = ['address[contains(attribute::class, "fullAddress")]'=>['.//div[contains(attribute::class, "addressData")]'=>['text']]];

	
	
	
	
	
class StopWatch {
//from github https://gist.github.com/phybros/5766062
  private static $startTimes = array();
  public static function start($timerName = 'default'){
    self::$startTimes[$timerName] = microtime(true);
  }
  public static function elapsed($timerName = 'default'){
    return microtime(true) - self::$startTimes[$timerName];
  }
}
class crawlLinks {  
	public $client;// neo4j
	private $pdo;
	
	public $start_url =""; 
	public $base_url =""; //starting url
	public $slash_only=true;//  true, false
	public $data=[];
	public $atts=[];  
	public $capture = 'all';//all or null for default domain only, no 404s..
	public $i=0;//levels 
	public $four04s=[];
	public $redirected=[];
	public $redirectsTo=[];
	public $otherErrors=[];
	public $curl_timeout = 5000;//5 seconds default
	public $include_dirs=[];//array('http://www.example.com/products-and-services/');//[]; 
	public $exclude_dirs=array('https://www.siteraiser.com/book');// array('http://www.example.com/products-and-services/web-development');//[]; 
	public $uagent ='Mozilla/5.0 (Linux; U; Android 2.2.1; en-us; MB525 Build/3.4.2-107_JDN-9) AppleWebKit/533.1 (KHTML, like Gecko) Version/4.0 Mobile Safari/533.1';
		
	public function __construct(){	
	
	/* old
		$this->client = ClientBuilder::create()->addConnection('default', 'http://neo4j:admin@localhost:7474')->build(); // Example for HTTP connection configuration (port is optional)	
	*/	 
	$this->client = ClientBuilder::create()
   ->withDriver('bolt', 'bolt://superuser:admin@localhost:7687') // creates a bolt driver
   //  ->withDriver('https', 'https://localhost:7474', Authenticate::basic('superuser', 'admin')) // creates an http driver
   // ->withDriver('neo4j', 'neo4j://neo4j.test.com?database=my-database', Authenticate::oidc('token')) // creates an auto routed driver with an OpenID Connect token
  ->withDefaultDriver('bolt')
    ->build();	 
	//Run here
	$query = "CREATE CONSTRAINT ON (u:Url) ASSERT u.href IS UNIQUE;";
	$result = $this->client->run($query,[]);
		 
		 
		try {
		   $this->pdo = new PDO('mysql:host=localhost;dbname=crawl', 'root', '');			
		} catch (PDOException $e) {
			print "Error!: " . $e->getMessage() . "<br/>";
			die();
		}
	}
	
//MySql-----------	
	function inDB($table,$url){
	//	echo"<span style='color:red;'>$url</span>";
		$stmt =$this->pdo->prepare("SELECT * FROM $table WHERE url = ?");
		$stmt->execute(array(utf8_decode(html_entity_decode(urldecode($url))))); //use urldecode( ) to allow crawling of links with crufty spaces... needs more testing
		$res = $stmt->fetch(PDO::FETCH_ASSOC);
		
		if(!empty($res)){
			return true;///$res['url']
		}else{
			return false;
		}			
	}
	
	function addUrlToTable($table,$url){
	
		$query='INSERT INTO '.$table.' 
		(url)
		VALUES
		(?)';	
	
		$stmt=$this->pdo->prepare($query);
		$stmt->execute(array(utf8_decode(html_entity_decode(urldecode($url)))));//urldecode(urlencode(
		
		return $url;
	}	
	
	function countTable($table){
		
		$sql = "SELECT COUNT(*) FROM $table";
		if ($res = $this->pdo->query($sql)) {
			return $res->fetchColumn();
		}
	}
	
	function GetUrls($table){
		$urls=[];
		$stmt =$this->pdo->prepare("SELECT * FROM $table ORDER BY id");
		$stmt->execute(array());
		$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
			foreach($rows as $row){
				$urls[] = html_entity_decode(utf8_encode($row['url']));
			}
		//	echo'<pre>';
	//	var_dump($urls);
		//echo'</pre>';
		return $urls;
	}
	
	
	
	
//Neo4j-----------	
	
		
	
//	CREATE CONSTRAINT ON (u:Url) ASSERT u.href IS UNIQUE;
function urlNotFoundInGraph($url){
	$query = 'MATCH (url { href:$url }) RETURN url.href, count(url) as count';
	
	
	$results = $this->client->run($query,["url"=>$url]);
	





	if($results->count() === 0){
		
		return true;
	}else{
		
		return false;
	}
}	
	function truncateTable($table){
		return $this->pdo->exec("TRUNCATE TABLE $table");
	}	
//	CREATE CONSTRAINT ON (u:Url) ASSERT u.href IS UNIQUE;
function checkUrl($url)
{
	$this->i=0;
	$this->data=[];
	$this->truncateTable('to_crawl');
	$this->four04s =[];
	$this->redirected=[];
	$this->redirectsTo =[];
		
    $this->makeRequests($url);
	$this->setPagesWith404s();
	$msg = (isset($this->four04s[0]) ?'notfound':'found');
	if(rtrim($url[0], '/') !== parse_url($url[0], PHP_URL_SCHEME).'://'.parse_url($url[0], PHP_URL_HOST) && ! stristr( $url[0],'#',0) ){
		$msg.= (isset($this->redirected[0])  ?' redirected':'');
	}
	return $msg;
}

function isFile($url){
	if(  stristr( strtolower( $url),'.pdf')===false && stristr( strtolower( $url),'.jpg')===false && stristr( strtolower( $url),'.png')===false){	
		return false;
	}else{
		return true;
	}
}

function addUrl($pageUrl,$url){
	
	
	//|| parse_url($url, PHP_URL_HOST) == 'localhost'
 if(stristr( $pageUrl,'#',0) || stristr( $url,'#',0)) {return;}
 
	if(parse_url($pageUrl, PHP_URL_HOST) ==  parse_url($url, PHP_URL_HOST)){
			$type = 'internal';
		}else{
			$type = 'external';
	}
	if($this->urlNotFoundInGraph($pageUrl)){
		 if(parse_url($pageUrl, PHP_URL_HOST) == parse_url($this->start_url, PHP_URL_HOST)){
			 $type = 'internal';
		 }
		 $query = 'CREATE (url:Url { href: $pageUrl, type:$type})';
		 $this->client->run($query,["pageUrl"=>$pageUrl,"type"=>$type]);
	
	}
	if($this->urlNotFoundInGraph($url)){
		 if(parse_url($url, PHP_URL_HOST) != parse_url($this->start_url, PHP_URL_HOST)){
			 $type = 'external';
		 }
		 
		 $query = 'CREATE (url:Url { href: $url, type:$type})';
		 $this->client->run($query,["url"=>$url,"type"=>$type]);
	}
	$query = 'MATCH (u1:Url { href: $pageUrl}), (u2:Url { href: $url}) 
	CREATE (u1)-[:references]->(u2)';//unique
	$this->client->run($query,["pageUrl"=>$pageUrl,"url"=>$url]);
}	
function addAtts($pageUrl){
	
	
//	CREATE CONSTRAINT ON (u:Url) ASSERT u.href IS UNIQUE;
//Add indexes...
/* Get pages pointing to url:
//MATCH (u1:Url)-[:references]-> (u2:Url { href: "http://www.siteraiser.com/customer-resources"}) return u1
*/	
	
	if(1){
			
		$properties = $groups =[];
		if(isset($this->atts[$pageUrl])){
			$properties = $this->atts[$pageUrl];
		}
		
		
		
		foreach($properties as $group => $items){	
		
			foreach($items as $item => $pair){			
				$array=[];
				foreach($pair as $name => $value){
					$array[$name] = $value;
				}				
				$groups[$group][]=$array;
			}		
		}			
	
				
		foreach($groups as $group => $items){
			
			$query='MATCH (u:Url { href:$pageUrl})
			CREATE (u)-[:has_group]->(g:Group { group: $group})';//		
			$this->client->run($query,["pageUrl"=>$pageUrl,"group"=>$group]);
		
			foreach($items as $item => $value){	
				$query='MATCH  (u:Url { href:$pageUrl})-[:has_group]->(g:Group { group:$group})
				CREATE (g)-[:has_item]->(i:Item { itemID: $item})';//		
				$this->client->run($query,["pageUrl"=>$pageUrl,"group"=>$group,"item"=>$item]);
				
				
			
				
				foreach($value as $property => $content){	
					$query='MATCH  (u:Url { href:$pageUrl})-[:has_group]->(g:Group { group:$group})-[:has_item]->(i:Item { itemID: $item})
					CREATE (i)-[:has_property]->(p:Property {property:$property,content:$content})';//		
					$this->client->run($query,["pageUrl"=>$pageUrl,"group"=>$group,"item"=>$item,"property"=>$property,"content"=>$content]);	
		
				}		 
				
			}	
		}
	
			
	}
	
		
}
function getPageList($type='internal'){
	
		$urls=[];
		$query="MATCH (u:Url) WHERE u.type = '".$type."'
		RETURN u.href";
		$results = $this->client->run($query);
		
		
		
		
			// A row is a \Laudis\Neo4j\Types\CypherMap
foreach ($results as $result) {

 $urls[] = $result->get('u.href');
  
}
	
	/*	
		
		foreach ($result->getRecords() as $record) {
			$urls[] = $record->value('u.href');
		}
		*/
		return $urls;
}
function getAttList($pageUrl,$exclude=''){
	
	$attsList=[];
	
	$query='
	MATCH (u:Url{href:$pageUrl})-[r1:has_group]->(g)-[r2:has_item]->(i) RETURN g.group AS groupname,i.itemID AS iid ORDER BY i.itemID';
	$results = $this->client->run($query,["pageUrl"=>$pageUrl]);
foreach ($results as $result) {
		$attsList[$result->get('groupname')][] = $result->get('iid');
	}
	
	$attsListO=[];
	foreach ($this->data as $orderedgroup => $g){
		foreach ($attsList as $group => $g2){
		if($orderedgroup == $group){			
				$attsListO[$orderedgroup] = $g2;
			}	
		}
	}
	
	
	unset($attsList);
	
	
	$theseAtts=[];
	
	foreach ($attsListO as $group => $itemIDs) {
		
		foreach($itemIDs as $itemID){
			$query='MATCH (u:Url{href:$pageUrl})-[r1:has_group]->(g:Group { group:$group})-[r2:has_item]->(i:Item {itemID:$itemID})-[:has_property]->(p) RETURN p.property AS pproperty, p.content AS pcontent ORDER BY i.itemID';
			$results = $this->client->run($query,["pageUrl"=>$pageUrl,"group"=>$group,"itemID"=>$itemID]);
			foreach ($results as $result) {
				if($result->get('pcontent') !=''){
					$theseAtts[$group][][$result->get('pproperty')] =$result->get('pcontent');
				}
			}
		}
	}		
	return $theseAtts;
	/**/	
}
	
	
	function clear(){
		$this->truncateTable('urls_captured');
		$this->truncateTable('to_crawl');
		$this->truncateTable('crawled');
		
		
		
		$query = "MATCH (n)
		DETACH DELETE n";
		$results = $this->client->run($query);
		
		//echo '<pre>';
	//	var_dump($result);
	//	echo '</pre>';
	
	


	
		if(count($results)===0 ){
			return true;
		}else{
			return false;
		}
	}
	function listByRelCount(){
		$query = "MATCH (n)<-[r]-() WHERE NOT EXISTS(n.is404) AND n.type = 'internal'
		WITH n, count(r) as c
		RETURN n.href, c
		ORDER BY c DESC
		LIMIT 30";
		$result = $this->client->run($query);
		$node_options = '';
		foreach ($results as $result) {
		echo '<br>'.$result->get('n.href').'--'.$result->get('c');
		}
	}
	
	
	
function setPagesWith404s(){
	foreach($this->four04s as $url){
		$query = 'MATCH (u { href:$url }) SET u.is404 = "1"';	
		$this->client->run($query,["url"=>$url]);			
	}
	foreach($this->redirected as $url){
		$query = 'MATCH (u { href:$url }) SET u.redirected = "1"';	
		$this->client->run($query,["url"=>$url]);			
	}
}
function getPagesWith404s(){
	$this->setPagesWith404s();
	$query = "MATCH (n:Url { is404: '1'})<-[r]-(n2:Url)
	WITH n,n2, count(r) as c
	RETURN n.href, n2.href, c
	ORDER BY c DESC
	LIMIT 50";
	echo'<br>Pages containing 404s';
	$result = $this->client->run($query);
	foreach ($results as $result) {
		echo '<br> 404: '.$result->get('n.href').' is on page '.$result->get('n2.href').'--'.$result->get('c');
	}
		
	
}	
function getPagesWithExternal404s(){
	
	$query = "MATCH (n:Url { is404: '1'})<-[r]-(n2:Url) WHERE n.type = 'external' AND n2.type = 'internal'
	WITH n,n2, count(r) as c
	RETURN n.href, n2.href, c
	ORDER BY c DESC
	LIMIT 50";
	echo'<br>Pages containing 404s';
	$result = $this->client->run($query);
	foreach ($results as $result) {
		echo '<br> 404: '.$result->get('n.href').' is on page '.$result->get('n2.href').'--'.$result->get('c');
	}
	$query = "MATCH (n:Url { redirected: '1'})<-[r]-(n2:Url) WHERE n.type = 'external' AND n2.type = 'internal'
	WITH n,n2, count(r) as c
	RETURN n.href, n2.href, c
	ORDER BY c DESC
	LIMIT 50";
	echo'<br>Pages containing redirected urls';
	$result = $this->client->run($query);
	foreach ($results as $result) {
		echo '<br> URL is a redirect and : '.$result->get('n.href').' is on page '.$result->get('n2.href').'--'.$result->get('c');
	}
	
}
	
function getDotHrefs($pageUrl,$url){
	//determine ../ type of relative path
	
	while(strpos($url, '../') === 0 ){

		$url = substr($url, 3);

		$temp = explode('/',parse_url(rtrim($pageUrl,"/"), PHP_URL_PATH));

		array_pop( $temp );

		$pageUrl = implode('/',$temp);
	}

return ltrim($pageUrl.'/'.$url,"/"); 
}	
	
	
function innerHTML($element)
{
    $doc = $element->ownerDocument;
    $html = '';
    foreach ($element->childNodes as $node) {
        $html .= $doc->saveHTML($node);
    }
    return $html;
}
	 
	 
function innerText($element)
{
   return $this->stripHTML($this->innerHTML($element));
}
	 
function stripHTML($html){
		//$html = str_replace(array(''), '', $html);		
		
		$html = preg_replace('~<\s*\bscript\b[^>]*>(.*?)<\s*\/\s*script\s*>~is', '', $html);//remove scripts
		$html = preg_replace('~<\s*\bstyle\b[^>]*>(.*?)<\s*\/\s*style\s*>~is', '', $html);
		$html = preg_replace('~<\s*\bnav\b[^>]*>(.*?)<\s*\/\s*nav\s*>~is', '', $html);
		$html = preg_replace('#<br\s*/?>#i', "\n", $html);
		$html = preg_replace('#<p\s*/?>#i', "\n", $html);$html = preg_replace('#</p>#i', "\n", $html);
		$html = preg_replace('#<h1\s*/?>#i', "\n", $html);$html = preg_replace('#</h1>#i', "\n", $html);
		$html = preg_replace('#<h2\s*/?>#i', "\n", $html);$html = preg_replace('#</h2>#i', "\n", $html);
		$html = preg_replace('#<h3\s*/?>#i', "\n", $html);$html = preg_replace('#</h3>#i', "\n", $html);
		$html = preg_replace('#<h4\s*/?>#i', "\n", $html);$html = preg_replace('#</h4>#i', "\n", $html);
		$html = preg_replace('#<h5\s*/?>#i', "\n", $html);$html = preg_replace('#</h5>#i', "\n", $html);
		$html = preg_replace('#<h6\s*/?>#i', "\n", $html);$html = preg_replace('#</h6>#i', "\n", $html);
		$html = preg_replace('#<div\s*/?>#i', "\n", $html);$html = preg_replace('#</div>#i', "\n", $html);
		
		$content1=strip_tags($html);	
		$order = array("\r\n", "\n", "\r");
		$replace = ' ';
		// Processes \r\n's first so they aren't converted twice.
		$content1 = str_replace($order, $replace, $content1);
		//&nbsp replacement from: https://stackoverflow.com/questions/28704562/how-to-remove-nbsp-from-a-utf-8-string		
		$content1 = str_replace("\xc2\xa0",' ',$content1); 		
				
		return $content1;
}	 
	 
	 
    function getAtts($html,$pageUrl){
	
 $nodeID=0;
 
        $doc = new DOMDocument();
        @$doc->loadHTML($html);     
		$selector = new DOMXPath($doc);	
		$selector->registerNamespace("html", "http://www.w3.org/1999/xhtml"); 
			
			
		$base_href = '';//base--href in header
		foreach( $selector->query('(//base)') as $b){
			$base_href = $b->getAttribute('href');
		}	
		$charset = '';//content type
		foreach( $selector->query('(//meta[contains(attribute::http-equiv, "Content-Type")])') as $c){
			$ctype = $c->getAttribute('content');
			$charArray = explode(';',$ctype);
			foreach($charArray as $val){
				$check = trim($val);
				$check = strtolower($check);
				if($check == 'charset=utf-8'){
					$charset = 'utf-8';					
				}			
			}
		}
		foreach( $selector->query('(//meta[contains(attribute::charset, "utf-8") or contains(attribute::charset, "UTF-8")])') as $c){
			$charset = strtolower($c->getAttribute('charset'));
		}	

		
        foreach ($this->data as $group => $elements){  
		
			foreach($elements as $elem => $atts){		
			
				$entries = $selector->query('(//'.$elem.")");
				foreach($atts as $query => $attributes){	
				
					foreach( $entries as $entry ) {
						
						foreach( $selector->query( $query, $entry) as $e){
							
							$nodeID = $nodeID + 1;						
							foreach($attributes as $attribute){	
								
								if($attribute=='innertext'){ 
									$str = $this->innerText($e);//$a2->nodeValue;    
									$this->atts[$pageUrl][$group][$nodeID][$attribute] = ($charset == 'utf-8'? $str :  utf8_decode($str));
								}else if($attribute=='text'){
									$str = $e->nodeValue;									
									$this->atts[$pageUrl][$group][$nodeID][$attribute] = ($charset == 'utf-8'? $str :  utf8_decode($str));					
								}else if($attribute == 'href'){//Make a fqurl
									$url = $e->getAttribute($attribute);
									
								$url =  (strtolower($charset) == 'utf-8'? $url : utf8_decode($url));////urldecode( ////utf8_decode(html_entity_decode(
									if(strpos($url, '#') === 0 || strpos($url, '?') === 0){ /* & group =='a' ..? */
										
										$path =parse_url($pageUrl, PHP_URL_SCHEME).	'://'.	parse_url($pageUrl, PHP_URL_HOST).	parse_url($pageUrl, PHP_URL_PATH);
										if($base_href != ''){
											$path = $base_href;
										}
										$final_value = $this->atts[$pageUrl][$group][$nodeID][$attribute] = $path.$url;    
									}else{
										//if starts with slash
										if(strpos($url, '/') === 0 ){								
												$final_value = $this->atts[$pageUrl][$group][$nodeID][$attribute]  = parse_url($pageUrl, PHP_URL_SCHEME).	'://'.	parse_url($pageUrl, PHP_URL_HOST) . $e->getAttribute($attribute);    											
										
										}else{ // Does not start with /, # or ? 
											if(parse_url($url, PHP_URL_SCHEME).	'://'.	parse_url($url, PHP_URL_HOST) == '://'){
											//Doesn't have a host in href	
												
												if(strpos($url, '../') === 0 ){
													$url = $this->getDotHrefs($pageUrl,$url);													
												}
												if($base_href != ''){
													$final_value = $this->atts[$pageUrl][$group][$nodeID][$attribute]  = rtrim($base_href, '/'). '/'.$url; 
												}else{
													$final_value = $this->atts[$pageUrl][$group][$nodeID][$attribute]  = parse_url($pageUrl, PHP_URL_SCHEME).	'://'.	parse_url($pageUrl, PHP_URL_HOST) . '/' .$url;    											
												}
											}else{
												//is a full url normal
												$final_value = $this->atts[$pageUrl][$group][$nodeID][$attribute] = $url; 
											}
										}											
									}
								}else{
									$str = $e->getAttribute($attribute);
									$final_value = $this->atts[$pageUrl][$group][$nodeID][$attribute] =($charset == 'utf-8'? $str :  utf8_decode($str));
								}	
																
								//Add nodes to graph
								if($attribute == 'href' && $group =='a'){
									
									//go back one /.
									if(substr($final_value, -2) == '/.'){
										$final_value = substr($final_value,0, -2);
									}

									$final_value = $this->addSlash($final_value);
									
									$this->ahrefs[$pageUrl][] = $final_value;// for filtering
									//force slash one way or another for base url
									
									$this->addUrl($pageUrl, $final_value);									
								}								
							}	
						}
					}
				}	
			}			
		}	
		
		
		/* Set attributes into neo4j */	
		
			
		$this->addAtts($pageUrl);
						
    }  
	//end of getAtts()
	
	
	public function include_dirs($url){ // crawl dir only
		
		foreach($this->include_dirs as $dir){
			//echo'<hr>'.$dir .'<br>';
			//echo $url;
			if(substr( $url , 0, strlen($dir) ) !== $dir){
				return false;
			}
		}
		
		return true;	
	}
	public function exclude_dirs($url){  //exclude directory (robot.txt check not completed yet)
		foreach($this->exclude_dirs as $dir){
			if(substr( $url , 0, strlen($dir) ) === $dir){
				return false;
			}			
		}
		
		return true;		
	}
	
	
	
	public function addSlash($url){
		
		// Page with link, not just a domain, return as is
		if(rtrim($url, '/') != parse_url($url, PHP_URL_SCHEME).'://'.parse_url($url, PHP_URL_HOST)){
			return $url;					
		}
		
		if($this->slash_only === true){
			if(rtrim($url, '/') == parse_url($url, PHP_URL_SCHEME).'://'.parse_url($url, PHP_URL_HOST) && substr($url,-1) != '/'){
				$url.='/';
			}	
		}else if($this->slash_only === false){
			if(rtrim($url, '/') == parse_url($url, PHP_URL_SCHEME).'://'.parse_url($url, PHP_URL_HOST) && substr($url,-1) == '/'){
				$url = rtrim($url, '/');
			}	
		}
		return $url;		
	}
	public function isSameDomain($url,$url2){
			$name = str_ireplace("www.", "", parse_url($url, PHP_URL_HOST));
			$name2 = str_ireplace("www.", "", parse_url($url2, PHP_URL_HOST));
			if($name == $name2){
				return true;
			}else{
				return false;
			}
			
	}
function encodeURI($url) {
    // http://php.net/manual/en/function.rawurlencode.php
    // https://developer.mozilla.org/en/JavaScript/Reference/Global_Objects/encodeURI
    $unescaped = array(
        '%2D'=>'-','%5F'=>'_','%2E'=>'.','%21'=>'!', '%7E'=>'~',
        '%2A'=>'*', '%27'=>"'", '%28'=>'(', '%29'=>')'
    );
    $reserved = array(
        '%3B'=>';','%2C'=>',','%2F'=>'/','%3F'=>'?','%3A'=>':',
        '%40'=>'@','%26'=>'&','%3D'=>'=','%2B'=>'+','%24'=>'$'
    );
    $score = array(
        '%23'=>'#'
    );
    return strtr(rawurlencode($url), array_merge($reserved,$unescaped,$score));
}
	public function sendRequest($pageUrl){	
			
		$httpCode ='';
		$buf = '';
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_USERAGENT, $this->uagent);
		curl_setopt($ch,CURLOPT_SSLVERSION, 6);
		curl_setopt($ch, CURLOPT_URL,$this->encodeURI($pageUrl));
		curl_setopt($ch, CURLOPT_COOKIEFILE, "");
		if( stristr( strtolower( $pageUrl),'.pdf',0) && stristr( strtolower( $pageUrl),'.jpg',0) && stristr( strtolower( $pageUrl),'.png',0)){	
		    curl_setopt($ch, CURLOPT_HEADER,         true);
			curl_setopt($ch, CURLOPT_NOBODY,         true); 					
		}
				
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
		curl_setopt($ch, CURLOPT_TIMEOUT,$this->curl_timeout);
		
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
		$buf = utf8_decode(htmlspecialchars_decode(curl_exec($ch)));//utf8_decode(html_entity_decode(curl_exec($ch)))
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);				
		$redirect_url = curl_getinfo($ch,CURLINFO_REDIRECT_URL);//CURLINFO_EFFECTIVE_URL -> for curl follow loca... 
				
		curl_close($ch);
			
			
			
			
			
		// Close handle
		if($httpCode == 200 || $httpCode > 400){				
			return ['buffer'=>$buf,'final_url'=>$pageUrl,'http_code'=>$httpCode];		
		} 
		if(isset($redirect_url) && $httpCode != 0){		
		
			return $this->sendRequest($redirect_url);	
		}else{	
			return ['buffer'=>$buf,'final_url'=>$pageUrl,'http_code'=>$httpCode];	
		}
	}
		
		
    function makeRequests($urls){
		$first_run=0;
		
		if($this->start_url == ''){
			$this->start_url = $urls[0];
			$this->base_url = 
			parse_url($urls[0], PHP_URL_SCHEME).
			'://'.
			parse_url($urls[0], PHP_URL_HOST);//could be fucntion
			$first_run=1;
		}
	
		
	
	
        foreach ($urls as $pageUrl) {   
		
			$pageUrl = $this->addSlash($pageUrl);
		
			ob_end_flush();
			ob_start();
			ob_implicit_flush();		
			echo '<span style="color:green;">'.$pageUrl .'</span><br>';	
			ob_flush();
			flush();
			
	
$this->addUrlToTable('crawled',$pageUrl);
		
		
		
			$page = $this->sendRequest($pageUrl);		
			$final_url = $page['final_url'];
			$final_url = $this->addSlash($final_url);
			$httpCode = $page['http_code'];			
			$buffer = $page['buffer'];
			
		
			
			
			if($final_url != $pageUrl && $httpCode != 404) {
				$this->redirectsTo[$pageUrl]= $final_url;
				$this->redirected[] = $pageUrl;						
							
				if( $this->isSameDomain($pageUrl,$final_url)){
					//$this->urlsCrawled[]=$final_url;	
					$this->addUrlToTable('crawled',$final_url);
					
					$this->start_url = 	$final_url;
				
					$this->base_url = parse_url($this->start_url, PHP_URL_SCHEME).'://'.parse_url($this->start_url, PHP_URL_HOST);//could be fucntion
					if($this->urlNotFoundInGraph($final_url)){
						$this->getAtts($buffer,$final_url);
					}
				}
			}else if($httpCode == 404){
				
				if($final_url != $pageUrl){
					$this->four04s[] = $final_url;
					$this->redirectsTo[$pageUrl]= $final_url;
					$this->redirected[] = $pageUrl;		
				}else{
					$this->four04s[] = $pageUrl;
				}
			}else if($buffer ==""){
				$this->otherErrors[$pageUrl] = $httpCode;//
			}else{
				$this->getAtts($buffer,$pageUrl);    
			}
        }
/*
CREATE TABLE `crawl`.`urls_captured` ( `id` INT(10) NOT NULL AUTO_INCREMENT , `url` VARCHAR(255) NOT NULL , PRIMARY KEY (`id`), UNIQUE (`url`)) ENGINE = InnoDB;
CREATE TABLE `crawl`.`to_crawl` ( `id` INT(10) NOT NULL AUTO_INCREMENT , `url` VARCHAR(255) NOT NULL , PRIMARY KEY (`id`), UNIQUE (`url`)) ENGINE = InnoDB;
CREATE TABLE `crawl`.`crawled` ( `id` INT(10) NOT NULL AUTO_INCREMENT , `url` VARCHAR(255) NOT NULL , PRIMARY KEY (`id`), UNIQUE (`url`)) ENGINE = InnoDB;
*/
//urlsCaptured is prefigured
//Filter urls
		//$this->crawl =[];
		$this->atts = [];
		$this->truncateTable('to_crawl');
			
		
		foreach($this->ahrefs as $url => $hrefs){
			foreach($hrefs as $href){
		
					$url = rtrim($href,'#');
					$url = $this->addSlash($url);
							
							
							
					if( stristr( $href,'#',0) & (parse_url($href, PHP_URL_HOST) == parse_url($this->start_url, PHP_URL_HOST))){//host possibly not necessary
						$urlparts = explode( $url , '#');
						if(! $this->inDB('urls_captured',$urlparts[0])){   
							$url = $urlparts[0] ;
						}else{
							 $this->addUrlToTable('urls_captured',$urlparts[0]);
						}										
					}
					
					$host = parse_url($url, PHP_URL_HOST);	
					$path = explode( '/',$url , 1);
					$path = end($path);																			
					$url = ($host == '' & (! stristr( $href,'#',0) ) ?  rtrim($this->base_url, '/').'/' : '' ).ltrim($path,'/');
					
				$url = $this->addSlash($url);
			//	echo'<hr><hr>'.	$url;
					//Build to crawl list	
					if (!stristr( $href,'#',0) && !filter_var($this->encodeURI($url), FILTER_VALIDATE_URL) === false && ($host == parse_url($this->start_url, PHP_URL_HOST) || $host == '') && ! $this->inDB('urls_captured',$url) && ! $this->inDB('crawled',$url) && ! in_array($url,$this->four04s) && ! in_array($url,$this->redirected)) {								
					
						if(  $this->exclude_dirs($url) && $this->include_dirs($url) && ! stristr( strtolower( $url),'.pdf',0) && ! stristr( strtolower( $url),'.jpg',0) && ! stristr( strtolower( $url),'.png',0)){
							
							$this->addUrlToTable('to_crawl',$url);
						}
					}
					//Build Capture array
					if($this->capture == '' ){
						if (($host == parse_url($this->start_url, PHP_URL_HOST) || $host == '') && ! in_array($url,$this->four04s) && ! in_array($url,$this->redirected) && ! stristr( $href,'#',0)){//host possibly not necessary
							 $this->addUrlToTable('urls_captured',rtrim($url,'#'));
						}
					}else if ($this->capture == 'all'){							
						$this->addUrlToTable('urls_captured',$url);
					}						
					
			}
		}
		
$this->ahrefs=[];
		
		
		$this->i = ($this->i - 1);
		while($this->countTable('to_crawl') > 0 && $this->i >= 0){
	
			$this->makeRequests($this->GetUrls('to_crawl'));
		}			
    }
	
	
}
$StopWatch = new StopWatch();
StopWatch::start();
$Crawl = new crawlLinks();
 
$Crawl->clear(); //clear graph & mySql
echo '<h3>Level: '.$Crawl->i=$crawl_level.'</h3>';
$Crawl->curl_timeout = $curl_timeout;
$Crawl->data = $data;
$Crawl->slash_only = (@$_GET['slash']?true:false);
$Crawl->makeRequests($reqs);
echo sprintf("Execution time: %s seconds - (Max $maxextime)", StopWatch::elapsed());
$Crawl->getPagesWith404s();
Echo'<hr>';
$Crawl->listByRelCount();
$pageUrls = $Crawl->getPageList($type = 'internal');
foreach($pageUrls as $pageUrl){
$atts[$pageUrl] = $Crawl->getAttList($pageUrl);
}
 foreach($atts as $url => $tags){
    echo'<h1>Url: '.$url.'</h1>Jump to:';
    foreach($tags as $tag => $elems){
        echo'<a href="#'.$tag.$url.'"> '.$tag.'</a> - ';
    }
    foreach($tags as $tag => $elems){
		
		if($tag !='a'){
			echo'<div id="'.$tag.$url.'" class="tag"><h2>Group: '.$tag.'</h2>';
			foreach($elems as $elem){
				
				echo '<div>';
				foreach($elem as $key => $attr){
					if( stristr( $attr,'youtube.com',0) || $tag !='IFrames' ){                      
						echo '<div>'.$key.' : '. $attr . '</div>';               
					}
				}
				echo'</div>';
			}
			echo'</div>';
		}
    }
    echo'<br><br>'; 
}
 
unset($atts);
 
?>
<div style="position:fixed; right:0px;top:10px;">
<a href="#crawl">crawl</a> - <a href="#urlsCrawled">urlsCrawled</a> - <a href="#urlsCaptured">urlsCaptured</a> - <a href="#four04s">four04s</a> - <a href="#redirected">redirected</a> - <a href="#external">external</a>
</div>
<h2 id="crawl">To Crawl</h2><div>
<?php
foreach($Crawl->GetUrls('to_crawl') as $url){
	echo  '<div>'.$url.'</div>';	
}
?>
</div>


<h2 id="urlsCrawled">urlsCrawled</h2><div>
<?php
foreach($Crawl->GetUrls('crawled') as $url){
	echo  '<div><a href="'.$url.'">'.$url.'</a></div>';	
}
?>
</div>


<h2 id="urlsCaptured">urlsCaptured</h2><div>
<?php
foreach($Crawl->GetUrls('urls_captured') as $url){
	echo  '<div>'.$url.'</div>';	
}
?>
</div>


<h2 id="four04s">four04s</h2><div>
<?php
foreach($Crawl->four04s as $url){
	echo  '<div>'.$url.'</div>';	
}
?>
</div>

<h2 id="redirected">redirected</h2><div>
<?php
 echo '<pre>';
echo htmlspecialchars(print_r($Crawl->redirectsTo, true));
echo '</pre>';
?>
</div>

<h2 id="otherErrors">otherErrors</h2><div>
<?php
foreach($Crawl->otherErrors as $err_code => $url){
	echo  '<div>'.$err_code.'--'.$url.'</div>';	
}
?>
</div>

<h2 id="external">external</h2><div>
<?php
if(@$_GET['external']){
	$host = parse_url($Crawl->start_url, PHP_URL_HOST);
	foreach($Crawl->GetUrls('urls_captured') as $url){	
		if($host != parse_url($url, PHP_URL_HOST)){
			
			StopWatch::start();
			
			
			if(stristr( $url,'#',0)){
			$urlparts = explode( $url , '#');
				$url = rtrim($urlparts[0], '#');
				
			}
			$url = $Crawl->addSlash($url);
			
			if($url !=''){
				echo  '<div><a href="'.$url.'">'.$url.'</a> '. $Crawl->checkUrl([$url]).'</div>';		 
			
				echo '<pre>';
				echo htmlspecialchars(print_r($Crawl->redirectsTo, true));
				echo '</pre>';	
			}
			
			
			echo sprintf("Execution time: %s seconds - (Max $maxextime)", StopWatch::elapsed());
		}
	}
	$Crawl->getPagesWithExternal404s();
	
}

include($_SERVER['DOCUMENT_ROOT'].'/pr.php');
?> 
</div>

<a href="browse.php" target="_blank">Browse / Search</a>

<style>
div.tag {border: solid 1px #000;}
div.tag > div {border: solid 1px #777;}
</style>
</body>
</html>
