<?php

class SimpleSAML_Metadata_MetaDataStorageHandlerPdo extends SimpleSAML_Metadata_MetaDataStorageSource
{
    private $pdo;
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
        $cfgHelp = SimpleSAML_Configuration::loadFromArray($config, 'pdo metadata source');

        // determine the table prefix if one was set
        $this->tableName = $cfgHelp->getString('prefix', '') . "metadata";
        $dsn = $cfgHelp->getString('dsn');

        $driverOptions = array();
        if ($cfgHelp->getBoolean('persistent', FALSE)) {
            $driverOptions[PDO::ATTR_PERSISTENT] = TRUE;
        }

        $this->pdo = new PDO($dsn, $cfgHelp->getValue('username', NULL), $cfgHelp->getValue('password', NULL), $driverOptions);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function getMetadataSet($metadataSet)
    {
        if (!in_array($metadataSet, $this->supportedSets)) {
            return array();
        }
        $returnSet = array();

        $stmt = $this->pdo->prepare("SELECT entity_id, entity_data FROM " . $this->tableName . " WHERE metadata_set = :metadata_set");
        $stmt->bindValue(":metadata_set", $metadataSet, PDO::PARAM_STR);
       $stmt->bindValue(":metadata_set", $metadataSet, PDO::PARAM_STR);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // FIXME: can data also be false if no entries are there?
        foreach ($data as $d) {
            $returnSet[$d['entity_id']] = json_decode($d['entity_data'], TRUE);
            // the 'entityid' key needs to be added to the entry itself...
            if (preg_match('/__DYNAMIC(:[0-9]+)?__/', $d['entity_id'])) {
                $returnSet[$d['entity_id']]['entityid'] = $this->generateDynamicHostedEntityID($metdataSet);
            } else {
                $returnSet[$d['entity_id']]['entityid'] = $d['entity_id'];
            }
        }

        return $returnSet;
    }

    public function getMetaData($entityId, $metadataSet)
    {
        if (!in_array($metadataSet, $this->supportedSets)) {
            return array();
        }

        $stmt = $this->pdo->prepare("SELECT entity_data FROM " . $this->tableName . " WHERE entity_id = :entity_id AND metadata_set = :metadata_set");
        $stmt->bindValue(":entity_id", $entityId, PDO::PARAM_STR);
        $stmt->bindValue(":metadata_set", $metadataSet, PDO::PARAM_STR);
        $stmt->execute();
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        // FIXME: if not exists it returns FALSE
        $entry = json_decode($data['entity_data'], TRUE);

        // the 'entityid' key needs to be added to the entry itself...
        if (preg_match('/__DYNAMIC(:[0-9]+)?__/', $entityId)) {
            $entry['entityid'] = $this->generateDynamicHostedEntityID($metadataSet);
        } else {
            $entry['entityid'] = $entityId;
        }

        return $entry;
    }

    public function addEntry($metadataSet, $entityId , $entityData)
    {
        if (!in_array($metadataSet, $this->supportedSets)) {
            return FALSE;
        }
        $stmt = $this->pdo->prepare("INSERT INTO " . $this->tableName . " (metadata_set, entity_id, entity_data) VALUES(:metadata_set, :entity_id, :entity_data)");
        $stmt->bindValue(":metadata_set", $metadataSet, PDO::PARAM_STR);
        $stmt->bindValue(":entity_id", $entityId, PDO::PARAM_STR);
        $stmt->bindValue(":entity_data", json_encode($entityData), PDO::PARAM_STR);
        $stmt->execute();
        //if (FALSE === $result) {
        //    throw new Exception("DB error: " . var_export($this->pdo->errorInfo(), TRUE));
        //}
        return 1 === $stmt->rowCount();
    }


}
