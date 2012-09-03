<?php
/**
 * TinyMemcacheClient - tiny, simple and pure-PHP alternative to Memcache and Memcached clients
 * 
 * @see https://github.com/memcached/memcached/blob/master/doc/protocol.txt
 * 
 * @link https://github.com/ptrofimov/tinymemcacheclient
 * @author Petr Trofimov <petrofimov@yandex.ru>
 */
class TinyMemcacheClient
{
	const EOL = "\r\n";
	
	const REPLY_STORED = 'STORED'; // Reply to storage commands: to indicate success
	const REPLY_NOT_STORED = 'NOT_STORED'; // Reply to storage commands: to indicate the data was not stored, but not because of an error
	const REPLY_EXISTS = 'EXISTS'; // Reply to storage commands: to indicate that the item you are trying to store with a "cas" command has been modified since you last fetched it
	

	const REPLY_OK = 'OK';
	const REPLY_ERROR = 'ERROR';
	const REPLY_DELETED = 'DELETED';
	const REPLY_NOT_FOUND = 'NOT_FOUND';
	const REPLY_TOUCHED = 'TOUCHED';
	
	private $_socket;
	
	public function __construct( $server )
	{
		$this->_socket = stream_socket_client( $server );
	}
	
	public function query( $query, $sendData = false, $data = null )
	{
		$query = $query . self::EOL . ( !$sendData ? '' : $data . self::EOL );
		fwrite( $this->_socket, $query );
		$line = fgets( $this->_socket );
		return substr( $line, 0, strlen( $line ) - 2 );
	}
	
	public function store( $cmd, $key, $flags = 0, $exptime = 0, $value = null )
	{
		return $this->query( "$cmd $key $flags $exptime " . strlen( $value ), true, $value );
	}
	
	public function set( $key, $value, $exptime = 0, $flags = 0 )
	{
		return $this->store( 'set', $key, $flags, $exptime, $value );
	}
	
	public function incr( $key, $value = 1 )
	{
		return $this->query( "incr $key $value" );
	}
	
	public function decr( $key, $value = 1 )
	{
		return $this->query( "decr $key $value" );
	}
	
	public function flushAll( $exptime = 0 )
	{
		return $this->query( "flush_all $exptime" );
	}
	
	public function append( $key, $value )
	{
		return $this->store( 'append', $key, 0, 0, $value );
	}
	
	public function prepend( $key, $value )
	{
		return $this->store( 'prepend', $key, 0, 0, $value );
	}
	
	public function add( $key, $value, $exptime = 0, $flags = 0 )
	{
		return $this->store( 'add', $key, $flags, $exptime, $value );
	}
	
	public function replace( $key, $value, $exptime = 0, $flags = 0 )
	{
		return $this->store( 'replace', $key, $flags, $exptime, $value );
	}
	
	public function del( $key )
	{
		$cmd = sprintf( 'delete %s' . "\r\n", $key );
		fwrite( $this->_socket, $cmd );
		$line = fgets( $this->_socket );
		return substr( $line, 0, strlen( $line ) - 2 );
	}
	
	public function get( $key )
	{
		$keys = is_array( $key ) ? $key : array( $key );
		
		$cmd = sprintf( 'get %s' . "\r\n", implode( ' ', $keys ) );
		fwrite( $this->_socket, $cmd );
		
		$values = array();
		
		while ( true )
		{
			$line = fgets( $this->_socket );
			$line = substr( $line, 0, strlen( $line ) - 2 );
			
			list( $cmd ) = explode( ' ', $line );
			
			if ( $cmd == 'END' )
			{
				if ( !is_array( $key ) )
				{
					$values[] = null;
				}
				break;
			}
			elseif ( $cmd == 'ERROR' )
			{
				throw new Exception( 'Error: client sent a nonexistent command name' );
			}
			elseif ( $cmd == 'CLIENT_ERROR' )
			{
				list( $cmd, $msg ) = explode( ' ', $line );
				throw new Exception( 'Error: the input doesn\'t conform to the protocol in some way: ' . $msg );
			}
			elseif ( $cmd == 'SERVER_ERROR' )
			{
				list( $cmd, $msg ) = explode( ' ', $line );
				throw new Exception( 'Error: some sort of server error prevents the server from carrying out the command: ' . $msg );
			}
			elseif ( $cmd == 'VALUE' )
			{
				list( $cmd, $key1, $flags, $length ) = explode( ' ', $line );
				var_dump( $line );
				$value = fread( $this->_socket, $length + 2 );
				$values[] = substr( $value, 0, strlen( $value ) - 2 );
			}
			else
			{
				throw new Exception( 'System error' );
			}
		}
		//var_dump( $values );
		return is_array( $key ) ? $values : $values[ 0 ];
	}
}