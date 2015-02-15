<?php
/**
 * Uniprot API
 * This class can query Uniprot database through it's API
 * The response text will transform to an array
 * @author Reage Yao
 * @version 0.1
 */
class UniprotAPI{
	/* API Address */
	const UNIPROT_API_BUS = 'http://www.uniprot.org/uniprot/?';
	const TAXONOMY_API_BUS = '';
	const CACHE_PATH = 'cache\';
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
	/**
	 *  Quick query gene&organism
	 */
	public function quickQuery($gene, $organism){
		$query = array(
			'gene' => $gene,
			'organism'	=>	$organism,
		);
		$this->ajaxReturn($this->parseXML($this->uniproQuery($query)));
	}
	/**
	 * DIY query array
	 */
	public function query($query) {
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
		$cache = file_get_contents(CACHE_PATH.$key);
		if ($cache) {
			//cached
			$response = $cache;
		}else{
			//not cached
			$url = self::UNIPROT_API_BUS.http_build_query( $parameters );
			$response = $this->getRemoteResponse($url);
			file_put_contents(CACHE_PATH.$key, $response);
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
		$xml2Array = A('XML2Array');
		$xmlParsedArray = $xml2Array->createArray($string);

		$simplifiedArray = $xmlParsedArray['uniprot']['entry'];
		unset($xmlParsedArray);
		
		/*If there were more than one entry that had been returned*/
		if (count($simplifiedArray) > 1 && !isset($simplifiedArray['accession'])) {
			$simplifiedArray = $simplifiedArray[0];
		}
		
		$tmp = self::pxGetMassAndSequence($simplifiedArray);
		$data = array(
			'function'		=>	self::pxGetFuntion($simplifiedArray),
			'mass'			=>	$tmp['mass'],
			'sequence'		=>	$tmp['seq'],
			'length'		=>	$tmp['length'],
			'activeSite'	=>	self::pxGetActiveSite($simplifiedArray),
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
				if(isset($comment['text']['@value'])){
					$ret = $comment['text']['@value'];
				}else{
					$ret = $comment['text'];
				}
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
			'seq'	=>	str_replace("\n", '', $totalContent['@value']),
			'mass'	=>	$totalContent['@attributes']['mass'],
			'length'=>	$totalContent['@attributes']['length'],
		);
		return $ret;
	}
	/**
	 * get active site from xml array
	 * @param unknown $xmlArray
	 * @return Ambigous <string, multitype:NULL >
	 */
	private static function pxGetActiveSite(&$xmlArray) {
		$totalFeature = $xmlArray['feature'];
		$ret = '';
		foreach ($totalFeature as $feature) {
			if ($feature['@attributes']['type'] == 'active site') {
				$ret[] = array(
						'position'		=>	$feature['location']['position']['@attributes']['position'],
						'description'	=>	$feature['@attributes']['description'],
				);
			};
		}
		return $ret;
	}
}
?>
