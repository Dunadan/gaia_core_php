<?php
namespace Gaia\DB\Callback;
use Gaia\DB\Callback;
use Gaia\DB\Transaction;

class MySQLi extends Callback implements \Gaia\DB\Iface {
    public function __construct(){
        $args = func_get_args();
        for( $i = 0; $i < 6; $i++) {
            if( ! isset( $args[ $i ] )) $args[$i] = NULL;
        }
        if( $args[0] instanceof \MySQLi ) {
            $mysqli = $args[0];
        } else {
            $mysqli = new \Mysqli( $args[0], $args[1], $args[2], $args[3], $args[4], $args[5]);
        }        
        $callbacks = array();
        $wrapper = $this;
        $callbacks['__tostring'] = function() use ( $mysqli ) {
            @ $res ='(Gaia\DB\MySQLi object - ' . "\n" .
                '  [affected_rows] => ' . $mysqli->affected_rows . "\n" .
                '  [client_info] => ' . $mysqli->client_info . "\n" .
                '  [client_version] => ' . $mysqli->client_version . "\n" .
                '  [connect_errno] => ' . $mysqli->connect_errno . "\n" .
                '  [connect_error] => ' . $mysqli->connect_error . "\n" .
                '  [errno] => ' . $mysqli->errno . "\n" .
                '  [error] => ' . $mysqli->error . "\n" .
                '  [field_count] => ' . $mysqli->field_count . "\n" .
                '  [host_info] => ' . $mysqli->host_info . "\n" .
                '  [info] => ' . $mysqli->info . "\n" .
                '  [insert_id] => ' . $mysqli->insert_id . "\n" .
                '  [server_info] => ' . $mysqli->server_info . "\n" .
                '  [server_version] => ' . $mysqli->server_version . "\n" .
                '  [sqlstate] => ' . $mysqli->sqlstate . "\n" .
                '  [protocol_version] => ' . $mysqli->protocol_version . "\n" .
                '  [thread_id] => ' . $mysqli->thread_id . "\n" .
                '  [warning_count] => ' . $mysqli->warning_count . "\n" .
                ')';
            return $res;
        };
        
        $callbacks['prep_args'] = $prep_args = function($query, array $args ) use ( $mysqli ){
            if( ! $args || count( $args ) < 1 ) return $query;
            return \Gaia\DB\Query::prepare( 
                $query, 
                $args, 
                function($v) use( $mysqli ){ return "'" . $mysqli->real_escape_string( $v ) . "'"; }
               );

        };
                
        $callbacks['execute'] = function( $query ) use ( $mysqli, $prep_args ){
            $args = func_get_args();
            array_shift($args);
            return $mysqli->query( $prep_args( $query, $args ) );
        };
        
        $callbacks['start'] = function () use ( $mysqli ){
            return $mysqli->query('START TRANSACTION');
        };
        
        $callbacks['autocommit'] = function ($mode) use ( $wrapper ){
            return ( $mode ) ? $wrapper->commit() : $wrapper->start();
        };
        
        $callbacks['hash'] = function () use ( $mysqli ){
            return spl_object_hash( $mysqli );
        };
        
        $callbacks['rollback'] = function () use ( $mysqli ){
            return $mysqli->query('ROLLBACK');
        };
        
        $callbacks['commit'] = function () use ( $mysqli ){
            return $mysqli->query('COMMIT');
        };
        
        $callbacks['prepare'] = function($query){
            trigger_error('unsupported method ' . __CLASS__ . '::' . __FUNCTION__, E_USER_ERROR);
            exit;
        };
        
        $callbacks['close'] = function() use ( $mysqli, $wrapper ) {
            Connection::remove( $wrapper );
            if( $wrapper->lock ) return FALSE;
            $rs = $mysqli->close();
            $wrapper->lock = TRUE;
            return $rs;
        };
        
        $callbacks['__get'] = function ($k) use ( $mysqli ){
            return $mysqli->$k;
        };
        
        $callbacks['__call'] = function ($method, array $args ) use ( $mysqli ){
            return call_user_func_array( array( $mysqli, $method ), $args );
        };
        
        $callbacks['query'] = function ( $query, $mode = MYSQLI_STORE_RESULT ) use ($mysqli, $wrapper){
            if( $wrapper->lock ) return FALSE;
            $res = $mysqli->query( $query, $mode );
            if( $res ) return $res;
            if( $wrapper->txn ) {
                Transaction::block();
                $wrapper->lock = TRUE;
            }
            return $res;
        };
        
        $callbacks['multi_query'] = function ( $query ) use ($mysqli, $wrapper) {
            if( $wrapper->lock ) return FALSE;
            $res = $mysqli->multi_query( $query );
            if( $res ) return $res;
            if( $wrapper->txn ) {
                Transaction::block();
                $wrapper->lock = TRUE;
            }
            return $res;
        };
        
        $callbacks['real_query'] = function( $query ) use ($mysqli, $wrapper ){
            if( $wrapper->lock ) return FALSE;
            $res = $mysqli->real_query( $query );
            if( $res ) return $res;
            if( $wrapper->txn ) {
                Transaction::block();
                $wrapper->lock = TRUE;
            }
            return $res;
        };
        
        $callbacks['isa'] = function( $name ) use( $wrapper, $mysqli ) {
            if( $wrapper instanceof $name ) return TRUE;
            if( $mysqli instanceof $name ) return TRUE;
            $name = strtolower( $name );
            if( $name == 'mysqli' ) return TRUE;
            //if( strpos($name, 'mysqli') !== FALSE ) return TRUE;
            return FALSE;
        };
                
        parent::__construct( $callbacks );
    }

}