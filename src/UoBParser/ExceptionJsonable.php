<?php

namespace UoBParser;

use \Exception;

class ExceptionJsonable extends Exception {

	public static function fromException($ex){ 

		if ($ex == null) 
			return null; 

		$ej = new ExceptionJsonable(
			$ex->getMessage(), 
			$ex->getCode(), 
			ExceptionJsonable::fromException($ex->getPrevious())
		);

		return $ej;
	}
	
	public function toArray(){
		
		$data = [];
		$data['message'] = $this->getMessage();
		$data['code'] = $this->getCode();
		
		$prev = $this->getPrevious();
		if ($prev !== null)
			$prev = (new ExceptionJsonable($prev))->toArray();
		
		$data['previous'] = $prev;
		
		return $data;
	}
}