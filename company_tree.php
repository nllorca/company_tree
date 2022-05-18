<?php

class Travel
{
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
	public function __construct($id, $price, $companyId)
	{
		$this->id = $id;
		$this->price = $price;
		$this->companyId = $companyId;
	}
}

class Company
{
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
	public function __construct($id, $name, $parentId)
	{
		$this->id = $id;
		$this->name = $name;
		$this->cost = 0;
		$this->children = [];
		$this->parentId = empty($parentId) ? null : $parentId;
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
}

class TestScript
{
	const COMPANIES_ENDPOINT = 'https://5f27781bf5d27e001612e057.mockapi.io/webprovise/companies';

	const TRAVELS_ENDPOINT = 'https://5f27781bf5d27e001612e057.mockapi.io/webprovise/travels';

	private $start, $current, $output, $elapsedTime;

	private function retrieveJson($jsonEndpoint)
	{
		$json = file_get_contents($jsonEndpoint);

		if (!$json) {
			return false;
		}

		$data = json_decode($json);

		return $data;
	}

	private function getCompanyFromJson($jsonEndpoint)
	{
		//I assume that the first element of the json is the root
		$company = null;

		$data = $this->retrieveJson($jsonEndpoint);

		$this->echoElapsedTime('Time elapsed retrieving companies JSON', $this->current);

		if (!empty($data)) {
			foreach ($data as $companyData) {
				if (empty($companyData->parentId)) {
					$company = new Company($companyData->id, $companyData->name, $companyData->parentId);
				} else {
					if (!is_null($company)) {
						$company->addChildCompany(new Company($companyData->id, $companyData->name, $companyData->parentId));
					}
				}
			}
		}

		$this->echoElapsedTime('Time elapsed building companies tree', $this->current);

		return $company;
	}


	private function getTravelsFromJson($jsonEndpoint)
	{
		$travels = [];

		$data = $this->retrieveJson($jsonEndpoint);

		$this->echoElapsedTime('Time elapsed retrieving travels JSON', $this->current);

		if (!empty($data)) {
			foreach ($data as $travelData) {
				$travels[] = new Travel($travelData->id, $travelData->price, $travelData->companyId);
			}
		}

		$this->echoElapsedTime('Time elapsed building travels array', $this->current);

		return $travels;
	}

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

		$company = $this->getCompanyFromJson(self::COMPANIES_ENDPOINT);

		if (empty($company)) {
			die('Error while getting companies from JSON' . PHP_EOL);
		}

		$travels = $this->getTravelsFromJson(self::TRAVELS_ENDPOINT);

		foreach ($travels as $travel) {
			$company->addTravel($travel);
		}

		$this->echoElapsedTime('Time elapsed adding travels to companies', $this->current);

		if ($this->output) {
			echo 'JSON OUTPUT:' . PHP_EOL;
			echo json_encode(get_object_vars($company), JSON_PRETTY_PRINT) . PHP_EOL;
		}

		if ($this->elapsedTime) {
			$this->echoElapsedTime('Total time', $this->start);
		}
	}
}

(new TestScript())->execute();
