<?php
trait Json
{
	public static function retrieveJson($jsonEndpoint)
	{
		$json = file_get_contents($jsonEndpoint);

		if (!$json) {
			return false;
		}

		$data = json_decode($json);

		return $data;
	}
}

class Travel
{
	use Json;

	public $id;
	public $price;
	public $companyId;

	/**
	 * Create a new Travel instance.
	 *
	 * @param  string  $id
	 * @param  string  $price
	 * @param  string  $companyId
	 * @return void
	 */
	public function __construct($attributes = [])
	{
		$this->id = $attributes['id'] ?: '';
		$this->price = $attributes['price'] ?: '';
		$this->companyId = $attributes['companyId'] ?: '';
	}

	/**
	 * Get array of Travel instances from a Json endpoint
	 *
	 * @param  string  $jsonEndpoint
	 * @return array
	 */
	public static function getTravelsFromJson($jsonEndpoint)
	{
		$travels = [];

		$data = self::retrieveJson($jsonEndpoint);

		if (!empty($data)) {
			foreach ($data as $travelData) {
				$attributes = (array) $travelData;

				$travels[] = new Travel($attributes);
			}
		}

		return $travels;
	}
}

class Company
{
	use Json;

	public $id;
	public $name;
	public $cost;
	public $children;
	private $parentId;
	private $parent;

	/**
	 * Create a new Company instance.
	 *
	 * @param  string  $id
	 * @param  string  $name
	 * @param  string  $parentId
	 * @return void
	 */
	public function __construct($attributes = [])
	{
		$this->id = $attributes['id'] ?: '';
		$this->name = $attributes['name'] ?: '';
		$this->parentId = $attributes['parentId'] ?: '';
		$this->cost = 0;
		$this->children = [];
		$this->parent = null;
	}

	/**
	 * Add child or root to Company Tree
	 *
	 * @param  Company  $company
	 * @return void
	 */
	public function addChildCompany(Company $company)
	{
		if ($company->parentId === $this->id) {
			$company->parent = &$this;
			$this->children[] = $company;
		} else {
			foreach ($this->children as $childCompany) {
				$result = $childCompany->addChildCompany($company);
				if ($result) {
					break;
				}
			}
		}
	}

	private function sumCost($cost)
	{
		$this->cost += $cost;

		if (!is_null($this->parent)) {
			$this->parent->sumCost($cost);
		}
	}

	/**
	 * Sums the price of a travel, to a Company and its ancestors  
	 *
	 * @param  Travel  $travel
	 * @return void
	 */
	public function addTravel(Travel $travel)
	{
		if ($travel->companyId === $this->id) {
			$this->sumCost($travel->price);
		} else {
			foreach ($this->children as $childCompany) {
				$result = $childCompany->addTravel($travel);
				if ($result) {
					break;
				}
			}
		}
	}

	/**
	 * Add travels to Company  
	 *
	 * @param  array  $travels
	 * @return void
	 */
	public function addTravels($travels)
	{
		foreach ($travels as $travel) {
			$this->addTravel($travel);
		}
	}

	/**
	 * Get Company instance from a Json endpoint
	 *
	 * @param  string  $jsonEndpoint
	 * @return Company
	 */
	public static function getCompanyFromJson($jsonEndpoint)
	{
		$company = null;

		$data = self::retrieveJson($jsonEndpoint);

		if (!empty($data)) {
			foreach ($data as $companyData) {
				$attributes = (array) $companyData;

				if (empty($companyData->parentId)) {
					$company = new Company($attributes);
				} else {
					if (!is_null($company)) {
						$company->addChildCompany(new Company($attributes));
					}
				}
			}
		}

		return $company;
	}
}

class TestScript
{
	const COMPANIES_ENDPOINT = 'https://5f27781bf5d27e001612e057.mockapi.io/webprovise/companies';

	const TRAVELS_ENDPOINT = 'https://5f27781bf5d27e001612e057.mockapi.io/webprovise/travels';

	private $start, $current, $output, $elapsedTime;

	private function echoElapsedTime($message, $from)
	{
		if (!$this->elapsedTime) {
			return;
		}

		echo $message . ': ' .  number_format((microtime(true) - $from), 6) . PHP_EOL . PHP_EOL;
		$this->current = microtime(true);
	}

	private function startTime()
	{
		$this->start = $this->current = microtime(true);
	}


	/*
	Execution options:
	-t Output the elapsed time of the script operations
	-o Output JSON result
	*/
	public function execute()
	{
		$options = getopt('t::o::');
		$this->output = isset($options['o']);
		$this->elapsedTime = isset($options['t']);

		$this->startTime();

		$company = Company::getCompanyFromJson(self::COMPANIES_ENDPOINT);

		$this->echoElapsedTime('Time elapsed getting companies from Json', $this->current);

		if (empty($company)) {
			die('Error while getting companies from JSON' . PHP_EOL);
		}

		$travels = Travel::getTravelsFromJson(self::TRAVELS_ENDPOINT);

		$this->echoElapsedTime('Time elapsed getting travels from Json', $this->current);

		$company->addTravels($travels);

		$this->echoElapsedTime('Time elapsed adding travels to companies', $this->current);

		$jsonOutput = json_encode(get_object_vars($company), JSON_PRETTY_PRINT);

		file_put_contents('output.json', $jsonOutput);
		
		if ($this->output) {
			echo 'JSON OUTPUT:' . PHP_EOL;
			echo $jsonOutput . PHP_EOL;
		}

		if ($this->elapsedTime) {
			$this->echoElapsedTime('Total time', $this->start);
		}
	}
}

(new TestScript())->execute();
