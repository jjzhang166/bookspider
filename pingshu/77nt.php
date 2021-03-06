<?php
	class C77NT
	{
		public $cache = array(
							"catalog" => 2592000,
							"book" => 604800,
							"chapter" => 2592000,
							"audio" => 2592000,
							"search" => 604800
						);

		public $redirect = 0;

		function GetName()
		{
			return "77nt";
		}

		function GetAudio($bookid, $chapter, $uri)
		{
			$uri = str_replace("Play", "zyurl", $uri);
			return $uri;
			$headers = http_get_headers($uri, "Location");

			if(!preg_match("/Location:([^\r\n]*)/i", $headers, $matches)){
				return "";
			}

			return 2 == count($matches) ? iconv("gb2312", "UTF-8", $matches[1]) : "";
		}

		function GetChapters($bookid)
		{
			list($path, $id) = split("-", $bookid);
			$uri = "http://www.77nt.com/" . $path . "/List_ID_" . $id . ".html";
			$html = http_get($uri);
			$html = str_replace("text/html; charset=gb2312", "text/html; charset=gb18030", $html);
			
			$xpath = new XPath($html);
			$iconuri = $xpath->get_attribute("//div[@class='conlist']/ul/li/img", "src");
			$summary = $xpath->get_value("//ul[@class='introbox']/p/span");
			$elements = $xpath->query("//ul[@class='compress']/ul/div/li/span/a");

			$host = parse_url($uri);
			$iconuri = 'http://' . $host["host"] . $iconuri;

			$chapters = array();
			foreach ($elements as $element) {
				$href = $element->getattribute('href');
				$chapter = $element->nodeValue;

				if(strlen($href) > 0 && strlen($chapter) > 0){
					$chapters[] = array("name" => $chapter, "uri" => 'http://' . $host["host"] . $href);
				}
			}

			$data = array();
			$data["icon"] = $iconuri;
			$data["info"] = $summary;
			$data["chapter"] = $chapters;
			return $data;
		}

		function GetBooks($uri)
		{
			$books = array();

			if(0 == strcmp($uri, 'http://www.77nt.com/1')){ // update
				$html = http_get('http://www.77nt.com/');
				$html = str_replace("text/html; charset=gb2312", "text/html; charset=gb18030", $html);

				$xpath = new XPath($html);
				$elements = $xpath->query("//div[@id='main']/div[2]/div/ul/li/a");
				foreach ($elements as $element) {
					$href = $element->getattribute('href');
					$book = $element->getattribute('title');

					if(strlen($href) > 0 && strlen($book) > 0){
						$bookid = basename($href, ".html");
						$books[basename(dirname($href)) . '-' . substr($bookid, 8)] = $book;
					}
				}
			} else if(0 == strcmp($uri, 'http://www.77nt.com/2')){ // top
				$html = http_get('http://www.77nt.com/');
				$html = str_replace("text/html; charset=gb2312", "text/html; charset=gb18030", $html);

				$xpath = new XPath($html);
				$elements = $xpath->query("//div[@id='main']/div[3]/div/ul/li/a");
				foreach ($elements as $element) {
					$href = $element->getattribute('href');
					$book = $element->getattribute('title');

					if(strlen($href) > 0 && strlen($book) > 0){
						$bookid = basename($href, ".html");
						$books[basename(dirname($href)) . '-' . substr($bookid, 8)] = $book;
					}
				}
			} else { // books
				$html = http_get($uri);
				$html = str_replace("text/html; charset=gb2312", "text/html; charset=gb18030", $html);

				$xpath = new XPath($html);
				$options = $xpath->query("//div[@class='page']/form/select/option");

				$pages = array();
				foreach ($options as $option) {
					$value = $option->getattribute('value');
					if($value != '0')
						$pages[] = dirname($uri) . '/' . basename($uri, ".html") . '-' . $value . ".html";
				}
				
				// page 1
				$result = array();
				$result[0] = $this->__ParseBooks($html);

				// other pages
				if(count($pages) > 0){
					$http = new HttpMultipleProxy("proxy.cfg");
					$r = $http->get($pages, array($this, '_OnReadBook'), &$result, 60);
					if(0 != $r){
						// log error
					}
				}

				if(count($pages) == count($result)){
					for($i = 0; $i < count($result); $i++){
						foreach($result[$i] as $bid => $book){
							$books[$bid] = $book;
						}
					}
				}
			}

			$data = array();
			$data["icon"] = "";
			$data["book"] = $books;
			return $data;
		}

		function GetCatalog()
		{
			$uri = 'http://www.77nt.com/';
			$html = http_proxy_get($uri, "xiaoya_6384@qq.com");
			$html = str_replace("text/html; charset=gb2312", "text/html; charset=gb18030", $html);
			$xpath = new XPath($html);
			$elements = $xpath->query("//div[@id='nav']/li[@class='menu_test']/a");

			$subcatalogs = array();
			$subcatalogs["最近更新"] = 'http://www.77nt.com/1';
			$subcatalogs["排行榜"] = 'http://www.77nt.com/2';

			$host = parse_url($uri);
			foreach ($elements as $element) {
				$href = $element->getattribute('href');
				$subcatalog = $element->nodeValue;
				if(strlen($href) > 0 && strlen($subcatalog) > 0){
					$subcatalogs[$subcatalog] = 'http://' . $host["host"] . '/' . $href;
				}
			}

			$catalog = array();
			$catalog["小说"] = $subcatalogs;
			return $catalog;
		}

		function Search($keyword)
		{
			$uri = 'http://www.77nt.com/SoClass.aspx';
			$data = 'class=' . urlencode(iconv("UTF-8", "gb2312", $keyword)) . '&submit=&ctl00%24Sodaohang=';
			$html = http_post($uri, $data);
			$html = str_replace("text/html; charset=gb2312", "text/html; charset=gb18030", $html);

			$xpath = new XPath($html);
			$options = $xpath->query("//div[@class='page']/form/select/option");

			$i = 0;
			$books = array();
			$pages = array();
			foreach ($options as $option) {
				$value = $option->getattribute('value');
				if($value != '0')
					$pages[] = 'http://www.77nt.com/Soclass.aspx?class=' . urlencode(iconv("UTF-8", "gb2312", $keyword)) . "&page=" . $i;
				++$i;
			}
			
			// page 1
			$result = array();
			$result[0] = $this->__ParseBooks($html);

			// other pages
			if(count($pages) > 0){
				$http = new HttpMultipleProxy("proxy.cfg");
				$r = $http->get($pages, array($this, '_OnReadBook'), &$result, 60);
				if(0 != $r){
					// log error
				}
			}

			if(count($pages) == count($result)){
				for($i = 0; $i < count($result); $i++){
					foreach($result[$i] as $bid => $book){						
						$books[$bid] = $book;
					}
				}
			}

			return array("book" => $books);
		}
		
		//---------------------------------------------------------------------------
		// private function
		//---------------------------------------------------------------------------
		function _OnReadBook($param, $i, $r, $header, $body)
		{
			if(0 != $r){
				//print_r("_OnReadBook $i error: $r\n");
				return -1;
			} else if(!stripos($body, "xiaoya_6384@qq.com")){
				// check html content integrity
				//print_r("_OnReadBook $i Integrity check error.\n");
				return -1;
			}

			$param[$i] = $this->__ParseBooks($body);
			//print_r("_OnReadBook $i: " . count($param[$i]) . "\n");
			return 0;
		}

		private function __ParseBooks($html)
		{
			$books = array();
			$html = str_replace("text/html; charset=gb2312", "text/html; charset=gb18030", $html);
			
			$xpath = new XPath($html);
			$elements = $xpath->query("//div[@class='border']/div/ul/li/a");
			foreach ($elements as $element) {
				$href = $element->getattribute('href');
				$book = $element->getattribute('title');

				if(strlen($href) > 0 && strlen($book) > 0){
					$bookid = basename($href, ".html");
					$books[basename(dirname($href)) . '-' . substr($bookid, 8)] = $book;
				}
			}

			return $books;
		}
	}

// require("php/dom.inc");
// require("php/util.inc");
// require("php/http.inc");
// require("php/http-multiple.inc");
// require("http-proxy.php");
// require("http-multiple-proxy.php");

// $obj = new C77NT();
// print_r($obj->GetCatalog()); sleep(2);
// print_r(count($obj->GetBooks("http://www.77nt.com/DouFuXiaoShui/DouFuXiaoShui.html"))); sleep(2);
// print_r($obj->GetChapters('DouFuXiaoShui-8436')); sleep(2); // http://www.77nt.com/DouFuXiaoShui/List_ID_8436.html
// print_r($obj->GetAudio('DouFuXiaoShui-8436', '1', "http://www.77nt.com/Play.aspx?id=8436&page=0")); sleep(2);
// print_r($obj->Search("单田芳")); sleep(2);
?>
