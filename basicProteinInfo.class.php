<?php
class EBIController{
	/* API Address */
	const UNIPROT_API_BUS = 'http://www.uniprot.org/uniprot/?';
	const TAXONOMY_API_BUS = '';
	private static $xml = null;
	private static $encoding = 'UTF-8';
	/* Acceptable fields in uniprot */
	public static $legalField = array(
		'accession'			=>	'UniProtKB AC', 
		'mnemonic'			=>	'Entry name [ID]', 
		'name'				=>	'Protein name [DE]', 
		'gene'				=>	'Gene name [GN]', 
		'organism'			=>	'Organism [OS]', 
		'taxonomy'			=>	'Taxonomy [OC]', 
		'host'				=>	'Virus host', 
		'existence'			=>	'Protein Existence [PE]', 
		'function'			=>	'Function', 
		'location'			=>	'Subcellular location', 
		'pathology'			=>	'Pathology & Biotech', 
		'ptm'				=>	'PTM/Processing', 
		'expression'		=>	'Expression',
		'interaction'		=>	'Interaction',
		'structure'			=>	'Structure',
		'seq'				=>	'Sequence',
		'familyAndDomains'	=>	'Family and Domains',
		'crossref'			=>	'Cross-references',
		'web'				=>	'Web resource',
		'date'				=>	'Date Of',
		'go'				=>	'Gene Ontology [GO]',
		'keyword'			=>	'Keyword [KW]',
		'citation'			=>	'Literature Citation',
		'proteomes'			=>	'Proteomes',
		'scope'				=>	'Cited For',
		'reviewed'			=>	'Reviewed',
		'active'			=>	'Active',
		'basket'			=>	'Basket',
		'cluster'			=>	'UniRef ID',
		'sequence'			=>	'UniParc ID',
		'jobs'				=>	'Jobs (last 7 days)',
	);
	/* Query gene&organism */
	public function quickQuery($gene, $organism){
		$query = array(
			'gene' => $gene,
			'organism'	=>	$organism,
		);
		$this->ajaxReturn($this->parseXML($this->uniproQuery($query)));
	}
	/**
	 * Build query string and get remote response by call getRemoteResponse()
	 * It will return the parsed xml
	*/
	public function uniproQuery($query, $format = 'xml', $columns = 'all', $include = 'no', $compress = 'no', $limit = 0, $offset = 0){
		$parameters = array();
		$parameters['query'] = '';
		$stopFlag = count($query) - 1;
		$i = 0;
		foreach ($query as $key => $value) {
			if(self::checkField($key)){
				if($i++ < $stopFlag)
					$parameters['query'] .= $key.':'.$value.' AND ';
				else
					$parameters['query'] .= $key.':'.$value;
			}else{
				return 0;
			}
		}
		$parameters['format'] = $format;
		if ($columns != 'all') $parameters['columns'] = $columns;
		if ($include != 'no') $parameters['include'] = $columns;
		if ($compress != 'no') $parameters['compress'] = $columns;
		if ($limit != 0) $parameters['limit'] = $columns;
		if ($offset != 0) $parameters['offset'] = $columns;
		$key = md5(serialize($parameters));
		$cache = F($key);
		if ($cache) {
			//有缓存
			$response = $cache;
		}else{
			//无缓存
			$url = self::UNIPROT_API_BUS.http_build_query( $parameters );
			$response = $this->getRemoteResponse($url);
			F($key, $response);
		}
		return $response;
	}
	/**
	 * Check whether the field is legal as an uniprot querying keyword
	 * If it is legal, the function will return it's short description
	 * If it is illegal, the function will return 0
	*/
	static function checkField($field){
		if (self::$legalField[$field]) {
			return self::$legalField[$field];
		}else{
			return 0;
		}
	}
	/**
	 * Get xml stream
	 * If neither curl nor file_get_contents is available, the function returns 0
	*/
	public function getRemoteResponse($url){
		if(function_exists('curl_version')){/* If curl is available*/
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_HEADER, 0);
			$ret = curl_exec($ch);
			curl_close($ch);
		}else if(ini_get('allow_url_fopen')){/* If file_get_contents is available*/
			$ret = file_get_contents($url);
		}else{
			$ret = 0;
		}
		return $ret;
	}
	/**
	 * Use DOMDocument object to parse uniprot's XML string
	 * @todo parse the entire XML
	*/
	public function parseXML($string){
		/*
		$data = array(
			'mass'	=>	'',
			'function'	=>	'',
		);
		$dom = new \DOMDocument('1.0');
		$dom->loadXML($string);
		$protein = $dom->getElementsByTagName('sequence');
		$sqStudy = $dom->getElementsByTagName('sequence');//Mass is an attribution of sequence tag
		foreach ($sqStudy as $s) {
			$data['mass'] = $s->getAttribute('mass');
		}
		$func = $dom->getElementsByTagName('comment');
		foreach ($func as $f) {
			if($f->getAttribute('type') == 'function') 
				$data['function'] = $f->nodeValue; //Function is a node of comment tag whose type attribution equals to function
		}
		*/
		$xml2Array = new XML2Array();
		$xmlParsedArray = $xml2Array->createArray($string);

		$simplifiedArray = $xmlParsedArray['uniprot']['entry'];
		unset($xmlParsedArray);

		$tmp = self::pxGetMassAndSequence($simplifiedArray);
		$data = array(
			'function'	=>	self::pxGetFuntion($simplifiedArray),
			'mass'		=>	$tmp['mass'],
			'sequence'	=>	$tmp['seq'],
			'length'	=>	$tmp['length'],
		);
		return $data;
	}
	/**
	 * get function information from xml array
	 */
	private static function pxGetFuntion(&$xmlArray){
		$totalComment = $xmlArray['comment'];
		$ret = '';
		foreach ($totalComment as $comment) {
			if($comment['@attributes']['type'] == 'function'){
				$ret = $comment['text']['@value'];
				break;
			}
		}
		return $ret;
	}
	/**
	 * get sequence information from xml array
	 */
	private static function pxGetMassAndSequence(&$xmlArray){
		$totalContent = $xmlArray['sequence'];
		$ret = array(
			'seq'	=>	$totalContent['@value'],
			'mass'	=>	$totalContent['@attributes']['mass'],
			'length'=>	$totalContent['@attributes']['length'],
		);
		return $ret;
	}
}
?>
