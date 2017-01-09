<? require_once 'global.php';
/**
 * Sitemap Generator based on http://github.com/o/sitemap-php
 */
class Sitemap {
	private $writer;
	private $domain;
	private $path = './';
	private $filename = 'sitemap';
	private $current_item = 0;
	private $current_sitemap = 0;

	const EXT = '.xml';
	const SCHEMA = 'http://www.sitemaps.org/schemas/sitemap/0.9';
    const XHTML = 'http://www.w3.org/1999/xhtml';
	const DEFAULT_PRIORITY = NULL;
	const ITEM_PER_SITEMAP = 50000;
	const SEPERATOR = '-';
	const INDEX_SUFFIX = 'index';

	public function __construct($domain) {
		$this->setDomain($domain);
	}

	public function setDomain($domain) {
		$this->domain = $domain;
		return $this;
	}

	private function getDomain() {
		return $this->domain;
	}

	private function getWriter() {
		return $this->writer;
	}

	private function setWriter(XMLWriter $writer) {
		$this->writer = $writer;
	}

	private function getPath() {
		return $this->path;
	}

	public function setPath($path) {
		$this->path = $path;
		return $this;
	}

	private function getFilename() {
		return $this->filename;
	}

	public function setFilename($filename) {
		$this->filename = $filename;
		return $this;
	}

	private function getCurrentItem() {
		return $this->current_item;
	}


	private function incCurrentItem() {
		$this->current_item = $this->current_item + 1;
	}


	private function getCurrentSitemap() {
		return $this->current_sitemap;
	}

	private function incCurrentSitemap() {
		$this->current_sitemap = $this->current_sitemap + 1;
	}

	private function startSitemap() {
		$this->setWriter(new XMLWriter());
		if ($this->getCurrentSitemap()) {
			$this->getWriter()->openURI($this->getPath() . $this->getFilename() . self::SEPERATOR . $this->getCurrentSitemap() . self::EXT);
		} else {
			$this->getWriter()->openURI($this->getPath() . $this->getFilename() . self::EXT);
		}
		$this->getWriter()->startDocument('1.0', 'UTF-8');
		$this->getWriter()->setIndent(true);
		$this->getWriter()->startElement('urlset');
		$this->getWriter()->writeAttribute('xmlns', self::SCHEMA);
        $this->getWriter()->writeAttribute('xmlns:xhtml', self::XHTML);
	}

	public function addItem($loc, $priority = self::DEFAULT_PRIORITY, $changefreq = NULL, $lastmod = NULL) {
		if (($this->getCurrentItem() % self::ITEM_PER_SITEMAP) == 0) {
			if ($this->getWriter() instanceof XMLWriter) {
				$this->endSitemap();
			}
			$this->startSitemap();
			$this->incCurrentSitemap();
		}
		$this->incCurrentItem();
		$this->getWriter()->startElement('url');
		$this->getWriter()->writeElement('loc', $this->getDomain() . $loc);
    $this->getWriter()->startElement('xhtml:link');
    $this->getWriter()->writeAttribute('rel', 'alternate');
    $this->getWriter()->writeAttribute('hreflang', 'en');
    $this->getWriter()->writeAttribute('href', $this->getDomain() . $loc);
    $this->getWriter()->endElement();

    if ($priority)
        $this->getWriter()->writeElement('priority', $priority);
		if ($changefreq)
			$this->getWriter()->writeElement('changefreq', $changefreq);
		if ($lastmod)
			$this->getWriter()->writeElement('lastmod', $this->getLastModifiedDate($lastmod));
		$this->getWriter()->endElement();
		return $this;
	}

	public function addLocaleItem($loc, $loc_da, $loc_en = NULL) {
		if (($this->getCurrentItem() % self::ITEM_PER_SITEMAP) == 0) {
			if ($this->getWriter() instanceof XMLWriter) {
				$this->endSitemap();
			}
			$this->startSitemap();
			$this->incCurrentSitemap();
		}
		$this->incCurrentItem();
		$this->getWriter()->startElement('url');
		$this->getWriter()->writeElement('loc', $this->getDomain() . $loc);
    $this->getWriter()->startElement('xhtml:link');
    $this->getWriter()->writeAttribute('rel', 'alternate');
    $this->getWriter()->writeAttribute('hreflang', 'en');
		if ($loc_en == NULL) { 
			$loc_en = $loc;
		}
    $this->getWriter()->writeAttribute('href', $this->getDomain() . $loc_en);
    $this->getWriter()->endElement();
		$this->getWriter()->startElement('xhtml:link');
    $this->getWriter()->writeAttribute('rel', 'alternate');
    $this->getWriter()->writeAttribute('hreflang', 'da');
    $this->getWriter()->writeAttribute('href', $this->getDomain() . $loc_da);
    $this->getWriter()->endElement();
		$this->getWriter()->endElement();
		return $this;
	}
	
	public function addSingleLocaleItem($loc, $locale) {
		if (($this->getCurrentItem() % self::ITEM_PER_SITEMAP) == 0) {
			if ($this->getWriter() instanceof XMLWriter) {
				$this->endSitemap();
			}
			$this->startSitemap();
			$this->incCurrentSitemap();
		}
		$this->incCurrentItem();
		$this->getWriter()->startElement('url');
		$this->getWriter()->writeElement('loc', $this->getDomain() . $loc);
    $this->getWriter()->startElement('xhtml:link');
    $this->getWriter()->writeAttribute('rel', 'alternate');
    $this->getWriter()->writeAttribute('hreflang', $locale);
    $this->getWriter()->writeAttribute('href', $this->getDomain() . $loc);
    $this->getWriter()->endElement();
		$this->getWriter()->endElement();
		return $this;
	}

	private function getLastModifiedDate($date) {
		if (ctype_digit($date)) {
			return date('Y-m-d', $date);
		} else {
			$date = strtotime($date);
			return date('Y-m-d', $date);
		}
	}

	public function endSitemap() {
		if (!$this->getWriter()) {
			$this->startSitemap();
		}
		$this->getWriter()->endElement();
		$this->getWriter()->endDocument();
	}

	public function createSitemapIndex($loc, $lastmod = 'Today') {
		$indexwriter = new XMLWriter();
		$indexwriter->openURI($this->getPath() . $this->getFilename() . self::SEPERATOR . self::INDEX_SUFFIX . self::EXT);
		$indexwriter->startDocument('1.0', 'UTF-8');
		$indexwriter->setIndent(true);
		$indexwriter->startElement('sitemapindex');
		$indexwriter->writeAttribute('xmlns', self::SCHEMA);
		for ($index = 0; $index < $this->getCurrentSitemap(); $index++) {
			$indexwriter->startElement('sitemap');
			$indexwriter->writeElement('loc', $loc . $this->getFilename() . ($index ? self::SEPERATOR . $index : '') . self::EXT);
			$indexwriter->writeElement('lastmod', $this->getLastModifiedDate($lastmod));
			$indexwriter->endElement();
		}
		$indexwriter->endElement();
		$indexwriter->endDocument();
	}

}

// cUrl handler to ping the Sitemap submission URLs for Search Engines…
	function myCurl($url){
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		return $httpCode;
	}

function generate_sitemap() {
	global $pdo;
	$sitemap = new Sitemap('http://onlinemusictutorials.com');
	
	$sitemap->addLocaleItem('', '/da', '/en');
	$sitemap->addLocaleItem('/en/account', '/da/konto');
	$sitemap->addLocaleItem('/en/terms', '/da/betingelser');
	$sitemap->addLocaleItem('/en/faq', '/da/faq');
	$sitemap->addLocaleItem('/en/contact', '/da/kontakt');

	$sitemap->addLocaleItem('/en/lessons/guitar', '/da/lektioner/guitar');
	$sitemap->addLocaleItem('/en/lessons/drums', '/da/lektioner/trommer');
	$sitemap->addLocaleItem('/en/lessons/piano', '/da/lektioner/klaver');
	$sitemap->addLocaleItem('/en/lessons/bass', '/da/lektioner/bas');

	$sitemap->addLocaleItem('/en/songs/guitar', '/da/sange/guitar');
	$sitemap->addLocaleItem('/en/songs/drums', '/da/sange/trommer');
	$sitemap->addLocaleItem('/en/songs/piano', '/da/sange/klaver');
	$sitemap->addLocaleItem('/en/songs/bass', '/da/sange/bas');

	$song_href = ['da' => '/da/sange', 'en' => '/en/songs'];
	$lesson_href = ['da' => '/da/lektioner', 'en' => '/en/lessons'];

	$access = ['da1' => 'alle', 'da2' => 'guitar', 'da3' => 'trommer', 'da4' => 'klaver', 'da5' => 'bas',
						'en1' => 'all', 'en2' => 'guitar', 'en3' => 'drums', 'en4' => 'piano', 'en5'=> 'bass'];

	try {
	$stmt = $pdo->prepare('SELECT access, slug, lang FROM songs');
	$stmt->execute();

	$songs = $stmt->fetchAll(PDO::FETCH_ASSOC);
	} catch(PDOException $e) {
	echo 'ERROR: ' . $e->getMessage();
	}

	foreach ($songs as &$item) {
	$index = $item['access'];
	$item['access'] = $access[$item['lang'].$index];
	$item['href'] = $song_href[$item['lang']].'/'.strtolower($item['access']).'/'.$item['slug'];
	if ($item['lang'] == 'da') {
		$sitemap->addSingleLocaleItem($item['href'], $item['lang']);
	} else {
		$item['href_en'] = $song_href['en'].'/'.strtolower($access['en'.$index]).'/'.$item['slug'];
		$item['href_da'] = $song_href['da'].'/'.strtolower($access['da'.$index]).'/'.$item['slug'];
		$sitemap->addLocaleItem($item['href_en'], $item['href_da']);
	}
	}

	try {
	$stmt = $pdo->prepare('SELECT slug_da, slug_en, access FROM lessons');
	$stmt->execute();

	$lessons = $stmt->fetchAll(PDO::FETCH_ASSOC);
	} catch(PDOException $e) {
		echo 'ERROR: ' . $e->getMessage();
	}

	foreach ($lessons as &$item) {
	$access_number = $item['access'];
	$index = 'en'.$access_number;
	$item['access'] = $access[$index];
	$item['href_en'] = $lesson_href['en'].'/'.strtolower($item['access']).'/'.$item['slug_en'];
	$index = 'da'.$access_number;
	$item['access'] = $access[$index];
	$item['href_da'] = $lesson_href['da'].'/'.strtolower($item['access']).'/'.$item['slug_da'];
	$sitemap->addLocaleItem($item['href_en'], $item['href_da']);
	}

	$sitemap->endSitemap();

	//Set this to be your site map URL
	$sitemapUrl = "http://onlinemusictutorials.com/sitemap.xml";

	//Google
	$url = "http://www.google.com/webmasters/sitemaps/ping?sitemap=".$sitemapUrl;
	echo $url;
	myCurl($url);

	//Bing / MSN
	$url = "http://www.bing.com/webmaster/ping.aspx?siteMap=".$sitemapUrl;
	myCurl($url);

	//ASK
	$url = "http://submissions.ask.com/ping?sitemap=".$sitemapUrl;
	myCurl($url);
}
?>
