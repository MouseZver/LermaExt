<?php

declare ( strict_types = 1 );

/*
	@ Author: MouseZver
	@ Email: mouse-zver@xaker.ru
	@ url-source: http://github.com/MouseZver/Lerma
	@ php-version 7.4
*/

namespace Nouvu\Database\LermaExt;

use Nouvu\Database\{ 
	Lerma, 
	InterfaceDriver 
};
use Error;
use Nouvu\Config\Config;

final class Mysql implements InterfaceDriver
{
	private $statement = null;
	
	private $query = null;
	
	private $result;
	
	private Lerma $lerma;
	
	private Config $config;
	
	private string $driver;
	
	private \mysqli $connect;
	
	private array $params;
	
	public function __construct ( Lerma $lerma, Config $config, string $driver )
	{
		$this -> lerma = $lerma;
		
		$this -> config = $config;
		
		$this -> driver = $driver;
		
		$this -> params = $this -> config -> get( "drivers.{$this -> driver}" );
		
		mysqli_report ( MYSQLI_REPORT_STRICT ); 
		
		$this -> connect();
	}
	
	private function connect()
	{
		try
		{
			$this -> connect = new \mysqli( 
				$this -> params['host'], 
				$this -> params['username'], 
				$this -> params['password'], 
				$this -> params['dbname'], 
				$this -> params['port']
			);
		}
		catch ( \mysqli_sql_exception $e )
		{
			$this -> config -> get( "ShemaExceptionConnect.{$this -> driver}" )( $e );
		}
		
		if ( $this -> connect -> connect_error ) 
		{
			throw new Error( sprintf ( $this -> config -> get( "errMessage.connect.{$this -> driver}" ), $this -> connect -> connect_errno, $this -> connect -> connect_error ) );
		}
		
		$this -> connect -> set_charset( $this -> params -> charset );
	}
	
	public function query( string $sql ): void
	{
		$this -> connect -> ping() ?: $this -> connect();
		
		$this -> query = $this -> connect -> query( $sql );
	}
	
	public function prepare( string $sql ): void
	{
		$this -> connect -> ping() ?: $this -> connect();
		
		$this -> statement = $this -> connect -> prepare( $sql );
	}
	
	public function fetch( int $int )
	{
		switch ( $int )
		{
			case Lerma :: FETCH_NUM:
			{
				return $this -> result() -> fetch_array( MYSQLI_NUM );
			}
			
			case Lerma :: FETCH_ASSOC:
			{
				return $this -> result() -> fetch_array( MYSQLI_ASSOC );
			}
			
			case Lerma :: FETCH_OBJ:
			{
				return $this -> result() -> fetch_object();
			}
			
			case Lerma :: MYSQL_FETCH_BIND:
			{
				return $this -> statement -> fetch();
			}
			
			case Lerma :: MYSQL_FETCH_FIELD:
			{
				return ( array ) $this -> result() -> fetch_field();
			}
			
			default:
			{
				return null;
			}
		}
	}
	
	public function fetchAll( int $int )
	{
		switch ( $int )
		{
			case Lerma :: FETCH_NUM:
			{
				return $this -> result() -> fetch_all( MYSQLI_NUM );
				
				break;
			}
			
			case Lerma :: FETCH_ASSOC:
			{
				return $this -> result() -> fetch_all( MYSQLI_ASSOC );
			
				break;
			}
			
			case Lerma :: MYSQL_FETCH_FIELD:
			{
				return $thiss -> result() -> fetch_fields();
			
				break;
			}
			
			default:
			{
				return null;
			}
		}
	}
	
	public function columnCount(): int
	{
		return $this -> connect -> field_count;
	}
	
	public function rowCount(): int
	{
		return $this -> result() -> num_rows;
	}
	
	public function InsertID(): int
	{
		return $this -> connect -> insert_id;
	}
	
	public function rollBack( ...$rollback ): bool
	{
		return $this -> connect -> rollback( ...$rollback );
	}
	
	public function beginTransaction( ...$rollback ): bool
	{
		return $this -> connect -> begin_transaction( ...$rollback );
	}
	
	public function commit( ...$commit ): bool
	{
		return $this -> connect -> commit( ...$commit );
	}
	
	public function isError(): void
	{
		$obj = $this -> statement ?: $this -> connect;
		
		if ( $obj -> errno )
		{
			throw new Error( $obj -> error );
		}
	}
	
	public function binding( array $binding ): void
	{
		$for = [ '' ];
		
		$count = 0;
		
		foreach ( $binding[0] AS $args )
		{
			if ( !in_array ( $type = gettype ( $args ), [ 'integer', 'double', 'string' ] ) )
			{
				throw new Error( "Invalid type {$type}" );
			}
			
			$for[0] .= $type{0};
			
			$count++;
		}

		for ( $i = 0; $i < $count; $for[] = &${ 'bind_' . $i++ } ){}
		
		$this -> statement -> bind_param( ...$for );

		foreach ( $binding AS $items )
		{
			$items = $this -> lerma -> executeHolders( $items );
			
			extract ( $items, EXTR_PREFIX_ALL, 'bind' );
			
			$this -> statement -> execute();
		}
	}
	
	public function bindResult( $result )
	{
		if ( is_null ( $this -> result ) )
		{
			return $this -> statement -> bind_result( ...$result );
		}
		
		throw new Error( $this -> config -> get( 'errMessage.statement.bindResult' ) );
	}
	
	public function close(): InterfaceDriver
	{
		if ( gettype ( $close = ( $this -> statement ?? $this -> query ) ) != 'boolean' && ! is_null ( $close ) )
		{
			$close -> close();
		}
		
		$this -> statement = $this -> query = $this -> result = null;
		
		return $this;
	}
	
	/*
		- Определение типа запроса в базу данных
	*/
	protected function result()
	{
		if ( ! is_null ( $this -> lerma -> statement ) )
		{
			return $this -> result ?: $this -> result = $this -> statement -> get_result();
		}
		
		return $this -> query;
	}
}
