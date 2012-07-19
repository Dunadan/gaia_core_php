<?php
namespace Gaia\Skein;

class Store implements Iface {
    
    protected $store;
    
    public function __construct( \Gaia\Store\Iface $store ){
        $this->store = $store;
    }
    
    public function count(){
        return Util::count( $this->shardSequences() );
    }
    
    public function get( $id ){
        if( is_array( $id ) ) return $this->multiget( $id );
        $res = $this->multiget( array( $id ) );
        return isset( $res[ $id ] ) ? $res[ $id ] : NULL;
    }
    
    protected function multiGet( array $ids ){
        return $this->store->get( Util::validateIds( $this->shardSequences(), $ids ) );
    }
    
    
    public function add( array $data ){
        $shard = Util::currentShard();
        $shard_key = 'shard_' . $shard;
        $sequence = $this->store->increment($shard_key);
        if( $sequence < 1 && ! $this->store->add( $shard_key, $sequence = 1) ) {
            throw new Exception('unable to allocate id');
        }
        
        $id = Util::composeId( $shard, $sequence );
        
        $this->store->set( $id, $data );
        
        $shards = $this->store->get('shards');
        if( ! is_array( $shards ) ) $shards = array();
        if( ! isset( $shards[ $shard ] ) ) {
            $shards[ $shard ] = 1;
            $this->store->set('shards', $shards );
        }
        // use the id to increment the sequence.
        return $id;
    }
    
    public function store( $id, array $data ){
        $ids = Util::validateIds( $this->shardSequences(), array( $id ) );
        if( ! in_array( $id, $ids ) ) throw new Exception('invalid id', $id );
        $this->store->set($id, $data );
        return TRUE;
    }
    
    public function ascending( $limit = 1000, $start_after = NULL ){
        return Util::ascending( $this->shardSequences(), $limit, $start_after );
    }
    
    public function descending( $limit = 1000, $start_after = NULL ){
        return Util::descending( $this->shardSequences(), $limit, $start_after );
    }
    
    public function shardSequences(){
        $shards = $this->store->get('shards');
        if( ! is_array( $shards ) ) return array();
        $shard_keys = array();
        
        foreach( array_keys( $shards ) as $shard ){
            $shard_keys[  'shard_' . $shard ] = $shard;
            
        }
        $result = array();
        foreach( $this->store->get( array_keys( $shard_keys ) ) as $shard_key => $sequence ){
            $sequence = strval($sequence );
            if( ! ctype_digit( $sequence ) ) continue;
            $result[ $shard_keys[ $shard_key ] ] = $sequence;
        }
        return $result;
    }
}
