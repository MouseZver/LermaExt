<?php

declare ( strict_types = 1 );

/*
	@ Author: MouseZver
	@ Email: mouse-zver@xaker.ru
	@ url-source: https://github.com/MouseZver/LermaExt
	@ php-version 8.0
*/

namespace Nouvu\Database\LermaExt;

use Nouvu\Database\Lerma;
use Nouvu\Database\InterfaceDriver;
use Error;
use Nouvu\Config\Config;

final class Sqlite implements InterfaceDriver
{
	private $statement = null;
	
	private $query = null;
	
	private $result;
	
	private \SQLite3 $connect;
	
	public function __construct ( private Lerma $lerma, private Config $config, private string $driver )
	{
		$this -> connect = new \SQLite3( $this -> config -> get( "drivers.{$driver}.db" ) );
	}
	
	public function isError( $obj = null ) {}
	
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
	
	public function fetch( int $int ): mixed
	{
		return match( $int ): mixed
		{
			Lerma :: FETCH_NUM		=> fn(): array | false => $this -> result() -> fetchArray( \SQLITE3_NUM ),
			Lerma :: FETCH_ASSOC	=> fn(): array | false => $this -> result() -> fetchArray( \SQLITE3_ASSOC ),
			Lerma :: FETCH_OBJ 		=> function (): object | null
			{
				if ( $res = $this -> result() -> fetchArray( \SQLITE3_ASSOC ) )
				{
					return ( object ) $res;
				}
				
				return null;
			},
			default	=> fn() => null,
		}();
	}
	
	public function fetchAll( int $int ): array | null
	{
		return match( $int ): array | null
		{
			Lerma :: FETCH_NUM, Lerma :: FETCH_ASSOC, Lerma :: FETCH_OBJ => function (): array
			{
				$all = [];
				
				while ( $res = $this -> fetch( $int ) ) 
				{ 
					$all[] = $res; 
				}

				return $all;
			},
			default	=> fn() => null,
		}();
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
