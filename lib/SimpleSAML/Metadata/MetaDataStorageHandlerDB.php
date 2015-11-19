<?php

class SimpleSAML_Metadata_MetaDataStorageHandlerDB extends SimpleSAML_Metadata_MetaDataStorageSource
{
    private $db;
    private $tableName;

    /**
     * All the metadata sets simpleSAMLphp supports
     */

    // FIXME: find these somewhere else, or just don't care...
    public $supportedSets = array (
        'adfs-idp-hosted',
        'adfs-sp-remote',
        'saml20-idp-hosted',
        'saml20-idp-remote',
        'saml20-sp-remote',
        'shib13-idp-hosted',
        'shib13-idp-remote',
        'shib13-sp-hosted',
        'shib13-sp-remote',
        'wsfed-idp-remote',
        'wsfed-sp-hosted'
    );

    public function __construct($config)
    {
        assert('is_array($config)');

        $globalConfig = SimpleSAML_Configuration::getInstance();
        $cfgHelp = SimpleSAML_Configuration::loadFromArray($config, 'db metadata source');

        
        require_once($cfgHelp->getString('scrybe_app_global_path',''));

		if (defined('LOCAL_LATEST_SERVICES_PATH'))
		{
			$services_path = LOCAL_LATEST_SERVICES_PATH;
		}
		else
		{
			$services_path = SERVICES_ROOT;	//LATEST_SERVICES_PATH
		}

		require_once($services_path . "db/DBInstanceManager.php");

        // determine the table prefix if one was set
        $this->tableName = $cfgHelp->getString('table_name','');
        $this->db = DBInstanceManager::getInstance()->getDBInstance();
        if( $this->db == null) {
            SimpleSAML_Logger::error("Failed to connect to database");
        }
    }

    public function getMetadataSet($metadataSet)
    {
        if (!in_array($metadataSet, $this->supportedSets)) {
            return array();
        }
        $returnSet = array();

        $SELECT = "SELECT entity_id \"entity_id\", entity_data \"entity_data\" FROM " . $this->tableName . " WHERE metadata_set = :metadata_set";
        $bind = array(array(":metadata_set", $metadataSet));
        $success = $this->db->prepareBindAndExecute($SELECT, $bind);        
        if( ! $success ) {
            SimpleSAML_Logger::error("MetaDataStorageHandlerDB.getMetadataSet error executing query:$SELECT, errorInfo:".
                    print_r($this->db->getErrorInfo(), true));
        } else {
            $data = $this->db->fetchAll();
            foreach ($data as $d) {
                $returnSet[$d['entity_id']] = json_decode($d['entity_data'], TRUE);
                // the 'entityid' key needs to be added to the entry itself...
                if (preg_match('/__DYNAMIC(:[0-9]+)?__/', $d['entity_id'])) {
                    $returnSet[$d['entity_id']]['entityid'] = $this->generateDynamicHostedEntityID($metdataSet);
                } else {
                    $returnSet[$d['entity_id']]['entityid'] = $d['entity_id'];
                }
            }
        }

        return $returnSet;
    }

    public function getMetaData($entityId, $metadataSet)
    {
		global $account_id;
		SimpleSAML_Logger::debug("MetaDataStorageHandlerDB::getMetaData() -> Account ID: $account_id");
        if (!in_array($metadataSet, $this->supportedSets)) {
            return array();
        }
        $SELECT = "SELECT entity_data \"entity_data\" FROM " . $this->tableName . " WHERE entity_id = :entity_id AND metadata_set = :metadata_set"; 
        $bind = array(array(":entity_id",$entityId), array(":metadata_set",$metadataSet));
		
		if (empty($account_id))
		{
			if (array_key_exists('sso_account_id', $_SESSION) && !empty($_SESSION['sso_account_id']))
			{
				$account_id = $_SESSION['sso_account_id'];
			}
		}
		
		if (!empty($account_id))
		{
			$SELECT .= ' AND account_id = :account_id';
			$bind[] = array(':account_id', $account_id);
		}
		
        $success = $this->db->prepareBindAndExecute($SELECT, $bind);        
        if( ! $success ) {
            SimpleSAML_Logger::error("MetaDataStorageHandlerDB.getMetaData error executing query:$SELECT, errorInfo:".
                    print_r($this->db->getErrorInfo(), true));
        } else {
        
            $data = $this->db->fetchArray();
            $entry = json_decode($data['entity_data'], TRUE);

            // the 'entityid' key needs to be added to the entry itself...
            if (preg_match('/__DYNAMIC(:[0-9]+)?__/', $entityId)) {
                $entry['entityid'] = $this->generateDynamicHostedEntityID($metadataSet);
            } else {
                $entry['entityid'] = $entityId;
            }
        }
        return $entry;
    }

    public function addEntry($metadataSet, $entityId , $entityData)
    {
        if (!in_array($metadataSet, $this->supportedSets)) {
            return FALSE;
        }
        
        $INSERT = "INSERT INTO " . $this->tableName . " (metadata_set, entity_id, entity_data) VALUES(:metadata_set, :entity_id, :entity_data)";
        $bind = array(array(":metadata_set", $metadataSet), array(":entity_id", $entityId),
                array(":entity_data", json_encode($entityData)));
        $success = $this->db->prepareBindAndExecute($INSERT, $bind);        
        if( ! $success ) {
            SimpleSAML_Logger::error("MetaDataStorageHandlerDB.addEntry error executing query:$INSERT, errorInfo:".
                    print_r($this->db->getErrorInfo(), true));
            return false;
        } else {
            return 1 === $this->db->getAffectedRowsCount();
        }
        
    }


}
