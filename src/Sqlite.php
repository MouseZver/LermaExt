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

final class Sqlite implements InterfaceDriver
{
	private $statement = null;
	
	private $query = null;
	
	private $result;
	
	private Lerma $lerma;
	
	private Config $config;
	
	private string $driver;
	
	private \SQLite3 $connect;
	
	private array $params;
	
	public function __construct ( Lerma $lerma, Config $config, string $driver )
	{
		$this -> lerma = $lerma;
		
		$this -> config = $config;
		
		$this -> connect = new \SQLite3( $this -> config -> get( "drivers.{$driver}.db" ) );
	}
	
	public function isError( $obj = null )
	{
		
	}
	
	public function query( string $sql ): void
	{
		$this -> query = $this -> connect -> query( $sql );
	}
	
	public function prepare( string $sql ): void
	{
		$this -> statement = $this -> connect -> prepare( $sql );
	}
	
	public function binding( array $binding ): void
	{
		foreach ( $binding AS $items )
		{
			foreach ( $this -> lerma -> executeHolders( $items, 1 ) AS $key => $item )
			{
				if ( is_int ( $key ) )
				{
					$this -> statement -> bindValue( $key, $item );
				}
				elseif ( strpos ( $key, ':' ) !== false )
				{
					$this -> statement -> bindParam( $key, $item );
				}
			}
			
			$this -> result = $this -> statement -> execute();
		}
	}
	
	public function close(): InterfaceDriver
	{
		if ( ! is_null ( $this -> statement ) )
		{
			$this -> statement -> close();
		}
		
		$this -> statement = $this -> query = $this -> result = null;
		
		return $this;
	}
	
	/*
		- Определение типа запроса в базу данных
	*/
	protected function result()
	{
		return $this -> query ?: $this -> result;
	}
	
	public function fetch( int $int )
	{
		switch ( $int )
		{
			case Lerma :: FETCH_NUM:
			{
				return $this -> result() -> fetchArray( SQLITE3_NUM );
				
				break;
			}
			
			case Lerma :: FETCH_ASSOC:
			{
				return $this -> result() -> fetchArray( SQLITE3_ASSOC );
				
				break;
			}
			
			case Lerma :: FETCH_OBJ:
			{
				if ( $res = $this -> fetch( Lerma :: FETCH_ASSOC ) )
				{
					return ( object ) $res;
				}
				
				return null;
				
				break;
			}
			
			default:
			{
				return null;
			}
		}
	}
	
	public function fetchAll( int $int ): ?array
	{
		switch ( $int )
		{
			case Lerma :: FETCH_NUM:
			case Lerma :: FETCH_ASSOC:
			{
				$all = [];
				
				while ( $res = $this -> fetch( $int ) ) 
				{ 
					$all[] = $res; 
				}

				return $all;
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
		return $this -> result() -> numColumns();
	}
	
	public function rowCount(): int
	{
		return 0;
	}
	
	public function InsertID(): int
	{
		return $this -> connect -> lastInsertRowID();
	}
	
	public function rollBack( ...$rollback ): bool
	{
		return $this -> connect -> exec( 'ROLLBACK' );
	}
	
	public function beginTransaction( ...$rollback ): bool
	{
		return $this -> connect -> exec( 'BEGIN TRANSACTION' );
	}
	
	public function commit( ...$commit ): bool
	{
		return $this -> connect -> exec( 'END TRANSACTION' );
	}
}
