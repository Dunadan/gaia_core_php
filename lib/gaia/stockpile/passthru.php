<?php
namespace Gaia\Stockpile;
use \Gaia\DB\Transaction;
//use \Gaia\Exception;

/**
 * Basic Wrapper class that allows us to easily punch out parts of the class.
 * this makes it possible to add an decorate behavior of a core object.
 * YAY DESIGN PATTERNS!
 * This pattern always confuses the shit out of new devs.
 */
abstract class Passthru implements IFace {
   
   /**
    * @Stockpile_Interface
    */
    protected $core;
    
   /**
    * @see Stockpile_Core::__construct();
    */
    public function __construct( Iface $core ){
        $this->core = $core;
    }

   /**
    * @see Stockpile_Interface::user();
    */
    public function user(){
        return $this->core->user();
    }

   /**
    * @see Stockpile_Interface::app();
    */
    public function app(){
        return $this->core->app();
    }
    
   /**
    * @see Stockpile_Interface::add();
    */
    public function add( $item_id, $quantity = 1, array $data = NULL ){
        return $this->core->add( $item_id, $quantity, $data );
    }
    
   /**
    * @see Stockpile_Interface::subtract();
    */
    public function subtract( $item_id, $quantity = 1, array $data = NULL ){
        return $this->core->subtract( $item_id, $quantity, $data );
    }
    
   /**
    * @see Stockpile_Interface::set();
    */
    public function set( $item_id, $quantity, array $data = NULL ){
        Transaction::start();
        try {
            $current = $this->get($item_id);
            if( Base::quantify( $current ) > 0 ) $this->subtract( $item_id, $current, $data );
            if( Base::quantify( $quantity ) == 0 ) {
                $result = $quantity;
            } else { 
                $result = $this->add( $item_id, $quantity, $data );
            }
            if( ! Transaction::commit() ) throw new \Gaia\Exception('database error');
            return $result;
        } catch( \Exception $e ){
            $this->handle( $e );
            throw $e;
        }
    }
    
   /**
    * @see Stockpile_Interface::get();
    */
    public function get( $item,  $with_lock = FALSE ){
        $ids = ( $scalar =  is_scalar( $item ) ) ? array( $item ) : $item;
        $rows = $this->fetch( $ids, $with_lock  );
        if( ! $scalar ) return $rows;
        return is_array( $rows ) && isset( $rows[ $item ] ) ? $rows[ $item ] : $this->defaultQuantity();
    }
    
   /**
    * @see Stockpile_Interface::all();
    */
    public function all(){
        return $this->fetch();
    }
    
   /**
    * @see Stockpile_Interface::fetch();
    */
    public function fetch( array $item_ids = NULL ){
        return $this->core->fetch( $item_ids );
    }
    
   /**
    * @see Stockpile_Interface::defaultQuantity();
    */
    public function defaultQuantity( $v = NULL ){
        return $this->core->defaultQuantity( $v );
    }

   /**
    * @see Stockpile_Interface::coreType();
    */
    public function coreType(){
        return $this->core->coreType();
    }
    
   /**
    * @see Stockpile_Interface::quantity();
    */
    public function quantity( $v = NULL ){
        return $this->core->quantity( $v );
    }
    
    public function storage( $name ){
        return $this->core->storage( $name );
    }
    
    public function handle( \Exception $e ){
        if( Transaction::inProgress() ) Transaction::rollback();
        return $e;
    }
    
   /**
    * Any methods we haven't handled so far, pass inward to the core to be handled.
    * That way if you know you have a logger in there with some custom methods, but it is wrapped,
    * you will be able to pass those calls through no matter what the nesting level.
    */
    public function __call( $method, $args ){
        return call_user_func_array( array( $this->core, $method ), $args );
    }
    
} // EOC


