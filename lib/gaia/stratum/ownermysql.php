<?php
namespace Gaia\Stratum;
use Gaia\DB;
use Gaia\Exception;

class OwnerMySQL implements Iface {
    
    protected $dsn;
    protected $table;
    protected $owner;
    
    public function __construct( $owner, $dsn, $table  ){
        $this->dsn = $dsn;
        $this->table = $table;
        $this->owner = $owner;
    }
    
    
    public function store( $constraint, $stratum ){
        $db = $this->db();
        $table = $this->table();
        if( DB\Transaction::inProgress() ) DB\Transaction::add( $db );

        $sql = "INSERT INTO $table 
            (`owner`, `constraint_id`, `constraint`, `stratum`) VALUES (%i, %s, %s, %i) 
            ON DUPLICATE KEY UPDATE `stratum` = VALUES(`stratum`)";
        $db->execute( $sql, $this->owner, sha1($constraint, TRUE), $constraint, $stratum );
    }
    
    public function delete( $constraint ){
        $db = $this->db();
        $table = $this->table();
        if( DB\Transaction::inProgress() ) DB\Transaction::add( $db );
        $sql = "DELETE FROM $table WHERE `owner` = %i AND `constraint_id` = %s";
        $rs = $db->execute( $sql, $this->owner, sha1($constraint, TRUE) );
        return $rs->affected() > 0;
    }
    
    public function batch( array $params = array() ){
        $sort = 'ASC';
        $start_after = NULL;
        $limit = NULL;
        
        if( isset( $params['sort'] ) ) $sort = $params['sort'];
        if( isset( $params['start_after'] ) ) $start_after = $params['start_after'];
        if( isset( $params['limit'] ) ) $limit = $params['limit'];
        $sort = strtoupper( $sort );        
        if( $sort != 'DESC' ) $sort = 'ASC';

        $db = $this->db();
        $table = $this->table();
        if( DB\Transaction::inProgress() ) DB\Transaction::add( $db );
        $where = array($db->prep_args('`owner` = %i', array( $this->owner ) ) );
        if( $start_after !== NULL ) $where[] = $db->prep_args("`constraint_id` > %s", array(sha1($start_after, TRUE)) );
        $where = ( $where ) ? 'WHERE ' . implode(' AND ', $where ) : '';
        $sql = "SELECT `constraint`, `stratum` FROM `{$table}` {$where} ORDER BY `constraint_id` $sort";
        if( $limit !== NULL && preg_match("#^([0-9]+)((,[0-9]+)?)$#", $limit ) ) $sql .= " LIMIT " . $limit;
        //print "\n$sql\n";
        $rs = $db->execute( $sql );
        while( $row = $rs->fetch() ) {
            $result[ $row['constraint'] ] = $row['stratum'];
        }
        $rs->free();
        return $result;
    }
    
    
    public function query( array $params = array() ){
        $search = NULL;
        $min = NULL;
        $max = NULL;
        $sort = 'ASC';
        $limit = NULL;
        $result = array();
        
        if( isset( $params['search'] ) ) $search = $params['search'];
        if( isset( $params['min'] ) ) $min = $params['min'];
        if( isset( $params['max'] ) ) $max = $params['max'];
        if( isset( $params['sort'] ) ) $sort = $params['sort'];
        if( isset( $params['limit'] ) ) $limit = $params['limit'];
        if( $limit !== NULL ) $limit = str_replace(' ', '', $limit );
        $sort = strtoupper( $sort );
        if( $sort != 'DESC' ) $sort = 'ASC';
        $db = $this->db();
        $table = $this->table();
        if( DB\Transaction::inProgress() ) DB\Transaction::add( $db );
        $where = array($db->prep_args('`owner` = %i', array( $this->owner ) ) );
        if( $search !== NULL ) $where[] = $db->prep_args("`stratum` IN( %s )", array($search) );
        if( $min !== NULL ) $where[] = $db->prep_args("`stratum` >= %i", array($min) );
        if( $max !== NULL ) $where[] = $db->prep_args("`stratum` <= %i", array($max) );
        $where = implode(' AND ', $where );
        $sql = "SELECT `constraint`, `stratum` FROM `$table` WHERE $where ORDER BY `stratum` $sort";
        if( $limit !== NULL && preg_match("#^([0-9]+)((,[0-9]+)?)$#", $limit ) ) $sql .= " LIMIT " . $limit;
        //print "\n$sql\n";
        $rs = $db->execute( $sql );
        while( $row = $rs->fetch() ) {
            $result[ $row['constraint'] ] = $row['stratum'];
        }
        $rs->free();
        return $result;
    }
    
    public function init(){
        $db = $this->db();
        $rs = $db->execute("SHOW TABLES LIKE %s", $this->table() );
        if( $rs->fetch() ) return;
        $db->execute( $this->schema() );
    }
    
    
    public function schema(){
        $table = $this->table();
        return 
            "CREATE TABLE IF NOT EXISTS $table (
                `rowid` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `owner` BIGINT UNSIGNED NOT NULL,
                `constraint_id` binary(20) NOT NULL,
                `constraint` VARCHAR(255) NOT NULL,
                `stratum` INT UNSIGNED NOT NULL,
                PRIMARY KEY (`rowid`),
                UNIQUE `owner_constraint` (`owner`,`constraint_id`),
                INDEX `owner_sort` (`owner`, `stratum`)
            ) ENGINE=InnoDB"; 
            
    }
    
    public function table(){
        return  $this->table;
    }
    
    protected function db(){
        $db = DB\Connection::instance( $this->dsn );
        if( ! $db->isa('mysql') ) throw new Exception('invalid db');
        if( ! $db->isa('gaia\db\extendediface') ) throw new Exception('invalid db');
        if( ! $db->isa('Gaia\DB\Except') ) $db = new DB\Except( $db );
        return $db;
    }
}
