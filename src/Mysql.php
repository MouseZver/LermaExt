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

final class Mysql implements InterfaceDriver
{
	private $statement = null;
	
	private $query = null;
	
	private $result;
	
	private \mysqli $connect;
	
	public function __construct ( private Lerma $lerma, private Config $config, private string $driver )
	{
		mysqli_report ( MYSQLI_REPORT_STRICT ); 
		
		$this -> connect();
	}
	
	private function connect()
	{
		$params = $this -> config -> get( 'drivers.' . $this -> driver );
		
		try
		{
			$this -> connect = new \mysqli( 
				$params['host'], 
				$params['username'], 
				$params['password'], 
				$params['dbname'], 
				( int ) $params['port']
			);
			
			if ( $this -> connect -> connect_error ) 
			{
				throw new Error( sprintf ( 
					$this -> config -> get( 'errMessage.connect.' . $this -> driver ), 
					$this -> connect -> connect_errno, 
					$this -> connect -> connect_error 
				) );
			}
			
			$this -> connect -> set_charset( $params['charset'] );
		}
		catch ( \mysqli_sql_exception $e )
		{
			$this -> config -> get( 'ShemaExceptionConnect.' . $this -> driver )( $e );
		}
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
	
	public function fetch( int $int ): mixed
	{
		return match( $int ): mixed
		{
			Lerma :: FETCH_NUM			=> $this -> result() -> fetch_array( \MYSQLI_NUM ),
			Lerma :: FETCH_ASSOC		=> $this -> result() -> fetch_array( \MYSQLI_ASSOC ),
			Lerma :: FETCH_OBJ 			=> $this -> result() -> fetch_object(),
			Lerma :: MYSQL_FETCH_BIND	=> $this -> statement -> fetch(),
			Lerma :: MYSQL_FETCH_FIELD	=> ( array ) $this -> result() -> fetch_field(),
			default						=> null,
		};
	}
	
	public function fetchAll( int $int ): mixed
	{
		return match( $int ): mixed
		{
			Lerma :: FETCH_NUM			=> $this -> result() -> fetch_all( \MYSQLI_NUM ),
			Lerma :: FETCH_ASSOC		=> $this -> result() -> fetch_all( \MYSQLI_ASSOC ),
			Lerma :: MYSQL_FETCH_FIELD	=> $this -> result() -> fetch_fields(),
			default						=> null,
		};
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
		$this -> result = null;
		
		$for = [ '' ];
		
		$count = 0;
		
		foreach ( $this -> lerma -> executeHolders( $binding[0] ) AS $args )
		{
			$short = [
				'integer' => 'i', 
				'double' => 'd', 
				'string' => 's',
				'NULL' => 's'
			];
			
			$type = gettype ( $args );
			
			if ( ! isset ( $short[$type] ) )
			{
				throw new Error( "Invalid type {$type}" );
			}
			
			$for[0] .= $short[$type];
			
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
		if ( ! is_null ( $this -> statement ) )
		{
			return $this -> result ?: $this -> result = $this -> statement -> get_result();
		}
		
		return $this -> query;
	}
}
