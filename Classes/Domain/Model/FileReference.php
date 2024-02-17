<?php

namespace Nng\Nnhelpers\Domain\Model;

class FileReference extends \TYPO3\CMS\Extbase\Domain\Model\FileReference {

	/**
	 * @var string
	 */
	protected $fieldname = 'image';

	/**
	 * @var string
	 */
	protected $tableLocal = 'sys_file';

	/**
	 * @var ?int
	 */
	protected ?int $uidLocal;
	
	/**
	 * @var int
	 */
	protected $cruserId = 1;
	
	/**
	 * @var int
	 */
	protected $sortingForeign = 1;
	
	/**
	 * @var int
	 */
	protected $sorting = 1;
	
	/**
	 * @var string
	 */
	protected $tablenames = '';

	/**
	 * @var string
	 */
	protected $title;
	
	/**
	 * @var string
	 */
	protected $description;
	
	/**
	 * @var string
	 */
	protected $alternative;
	
	/**
	 * @var string
	 */
	protected $link;
	
	/**
	 * @var string
	 */
	protected $crop;
	
	/**
	 * @var \Nng\Nnhelpers\Domain\Model\File
	 */
	protected $file;

	/**
	 * @var int
	 */
	protected $uidForeign;

	/**
	 * @var int
	 */
	protected $processingStatus;
	
	/**
	 * @var string
	 */
	protected $processingData;

	/**
	 * @var string
	 */
	protected $extension;
	
	/**
	* Constructor
	**/
	public function __construct() {
		$this->sorting = time();
	}
	
	/**
	 * @return \TYPO3\CMS\Core\Resource\FileReference
	 */
	public function getOriginalResource(): \TYPO3\CMS\Core\Resource\FileReference {
		return $this;
	}
	
	/**
	 * @return array
	 */
	public function getProperties() {
		return [
			'title' 		=> $this->getTitle(),
			'alternative'	=> $this->getAlternative(),
			'link'			=> $this->getLink(),
			'description'	=> $this->getDescription(),
			'crop'			=> $this->getCrop(),
			'publicUrl'		=> $this->getPublicUrl(),
		];
	}
	
	/**
	 * @return array
	 */
	public function getPublicUrl() {
		if ($file = $this->getFile()) {
			return $file->getFilepath();
		}
		return '';
	}
	
	/**
	 * @return array
	 */
	public function getProcessingData() {
		return (array) json_decode($this->processingData, true);
	}

	/**
	 */
	public function setProcessingData( $val ) {
		if (is_array($val)) $val = json_encode($val);
		$this->processingData = $val;
	}


	/**
	 * @return int
	 */
	public function getProcessingStatus() {
		return $this->processingStatus;
	}

	/**
	 */
	public function setProcessingStatus( $val ) {
		$this->processingStatus = $val;
	}
	
	/**
	 * Set uid_local
	 *
	 * @param int $uid_local
	 */
	public function setUidLocal($uid_local) {
		$this->uidLocal = $uid_local;
	}

	/**
	 * Get uid_local
	 *
	 * @return int
	 */
	public function getUidLocal() {
		return $this->uidLocal;
	}
	
	/**
	 * Set file
	 *
	 * @param \Nng\Nnhelpers\Domain\Model\File $file
	 */
	public function setFile($file) {
		$this->file = $file;
		$this->setUidLocal( $file->getUid() );
	}

	/**
	 * Get file
	 *
	 * @return \Nng\Nnhelpers\Domain\Model\File
	 */
	public function getFile() {
		return $this->file;
	}
	
	/**
	 * @return int
	 */
	public function getCruserId() {
		return $this->cruserId;
	}

	/**
	 * @param int $uidForeign
	 */
	public function setCruserId($cruserId) {
		$this->cruserId = $cruserId;
	}
	
	/**
	 * @return int
	 */
	public function getUidForeign() {
		return $this->uidForeign;
	}

	/**
	 * @param int $uidForeign
	 */
	public function setUidForeign($uidForeign) {
		$this->uidForeign = $uidForeign;
	}
	
	/**
	 * @return int
	 */
	public function getSortingForeign() {
		return $this->sortingForeign;
	}

	/**
	 * @param int $sortingForeign
	 */
	public function setSortingForeign($sortingForeign) {
		$this->sortingForeign = $sortingForeign;
	}
	
	/**
	 * @return int
	 */
	public function getSorting() {
		return $this->sorting;
	}

	/**
	 * @param int $sorting
	 */
	public function setSorting($sorting) {
		$this->sorting = $sorting;
	}
	
	/**
	 * @return string
	 */
	public function getFieldname() {
		return $this->fieldname;
	}

	/**
	 * @param string $fieldname
	 */
	public function setFieldname($fieldname) {
		$this->fieldname = $fieldname;
	}
	
	/**
	 * @return string
	 */
	public function getTableLocal() {
		return $this->tableLocal;
	}

	/**
	 * @param string $tableLocal
	 */
	public function setTableLocal($tableLocal) {
		$this->tableLocal = $tableLocal;
	}
	
	/**
	 * @return string
	 */
	public function getTablenames() {
		return $this->tablenames;
	}

	/**
	 * @param string $tablenames
	 */
	public function setTablenames($tablenames) {
		$this->tablenames = $tablenames;
	}
	
	/**
	 * @return  string
	 */
	public function getTitle() {
		return $this->title;
	}

	/**
	 * @param   $title  
	 * @return  self
	 */
	public function setTitle($title) {
		$this->title = $title;
		return $this;
	}

	/**
	 * @return  string
	 */
	public function getDescription() {
		return $this->description;
	}

	/**
	 * @param   string  $description  
	 * @return  self
	 */
	public function setDescription($description) {
		$this->description = $description;
		return $this;
	}

	/**
	 * @return  string
	 */
	public function getAlternative() {
		return $this->alternative;
	}

	/**
	 * @param   string  $alternative  
	 * @return  self
	 */
	public function setAlternative($alternative) {
		$this->alternative = $alternative;
		return $this;
	}

	/**
	 * @return  string
	 */
	public function getLink() {
		return $this->link;
	}

	/**
	 * @param   string  $link  
	 * @return  self
	 */
	public function setLink($link) {
		$this->link = $link;
		return $this;
	}

	/**
	 * @return  string
	 */
	public function getCrop() {
		return $this->crop;
	}

	/**
	 * @param   string  $crop  
	 * @return  self
	 */
	public function setCrop($crop) {
		$this->crop = $crop;
		return $this;
	}
}