<?php
/*
Plugin Name: KL GA Adapter
Plugin URI: https://github.com/educate-sysadmin/kl-ga-adapter
Description: Wordpress plugin providing PHP adapter to Google Analytics API
Version: 0.1
Author: b.cunningham@ucl.ac.uk
Author URI: https://educate.london
License: GPL2
*/

/* Link: https://github.com/googleapis/google-api-php-client */

require_once(plugin_dir_path( __FILE__ ).'vendor/google-api-php-client/vendor/autoload.php');

require_once('kl-ga-adapter-options.php');

class KLGA {
	
	/* Ref: https://developers.google.com/analytics/devguides/reporting/core/v4/quickstart/service-php */
	/**
	 * Initializes an Analytics Reporting API V4 service object.
	 *
	 * @return An authorized Analytics Reporting API V4 service object.
	 */
	public static function initializeAnalytics()
	{

	  // Use the developers console and download your service account
	  // credentials in JSON format. Place them in this directory or
	  // change the key file location if necessary.	  
	  $KEY_FILE_LOCATION = "";
	  if (substr(get_option('kl_ga_DeveloperKeyFileLocation'),0,1) != "/") {
		$KEY_FILE_LOCATION = __DIR__ .'/';
	  }
	  $KEY_FILE_LOCATION .= get_option('kl_ga_DeveloperKeyFileLocation');	  

	  // Create and configure a new client object.
	  $client = new Google_Client();
	  $client->setApplicationName(get_option('kl_ga_ApplicationName'));
	  $client->setAuthConfig($KEY_FILE_LOCATION);
	  $client->setScopes(['https://www.googleapis.com/auth/analytics.readonly']);
	  $ganalytics = new Google_Service_AnalyticsReporting($client);

	  return $ganalytics;
	}


	/**
	 * Sample: Queries the Analytics Reporting API V4.
	 *
	 * @param service An authorized Analytics Reporting API V4 service object.
	 * @return The Analytics Reporting API V4 response.
	 */
	public static function getReportSample($analytics) {

	  // Replace with your view ID, for example XXXX.
	  $VIEW_ID = "nnn...";

	  // Create the DateRange object.
	  $dateRange = new Google_Service_AnalyticsReporting_DateRange();
	  $dateRange->setStartDate("7daysAgo");
	  $dateRange->setEndDate("today");

	  // Create the Metrics object.
	  $sessions = new Google_Service_AnalyticsReporting_Metric();
	  $sessions->setExpression("ga:sessions");
	  $sessions->setAlias("sessions");

	  // Create the ReportRequest object.
	  $request = new Google_Service_AnalyticsReporting_ReportRequest();	  
	  $request->setViewId($VIEW_ID);
	  $request->setDateRanges($dateRange);
	  $request->setMetrics(array($sessions));	  

	  $body = new Google_Service_AnalyticsReporting_GetReportsRequest();
	  $body->setReportRequests( array( $request) );
	  return $analytics->reports->batchGet( $body );
	}
	
	/* KL mod of getReport */
	public static function getReport(
		$analytics, 
		$start, 
		$end, 
		$pageSize = 20,
		$metric, 
		$dimension = null, 		
		$order = null /* currently default pageviews desc ordering only */
	) {
		
	  // Create the DateRange object.
	  $dateRange = new Google_Service_AnalyticsReporting_DateRange();
	  $dateRange->setStartDate($start);
	  $dateRange->setEndDate($end);

	  // Create the Metrics object.
	  $sessions = new Google_Service_AnalyticsReporting_Metric();
	  $sessions->setExpression("ga:".$metric);
	  $sessions->setAlias($metric);

	  // Create the Dimension object
	  if ($dimension) {
		$dimensions = new Google_Service_AnalyticsReporting_Dimension;
		$dimensions->setName('ga:'.$dimension);
	  }
	  
	  // Work out the ordering (complex analytics with dimension only)
	  // Default to pageviews desc
	  if ($dimension) {
		$ordering = new Google_Service_AnalyticsReporting_OrderBy();
		$ordering->setFieldName("ga:pageviews");
		$ordering->setOrderType("VALUE");   
		$ordering->setSortOrder("DESCENDING");
	  }        

	  // Create the ReportRequest object.
	  $request = new Google_Service_AnalyticsReporting_ReportRequest();
	  $request->setViewId(get_option('kl_ga_ViewId'));
	  $request->setDateRanges($dateRange);
	  $request->setMetrics(array($sessions));
	  if ($dimension) {
		  $request->setDimensions(array($dimensions));
		  $request->setOrderBys($ordering);		  
	  }
	  
	  // Limit rows
	  $request->setPageSize($pageSize);  

	  $body = new Google_Service_AnalyticsReporting_GetReportsRequest();
	  $body->setReportRequests( array( $request) );
	  return $analytics->reports->batchGet( $body );
	}	



	/**
	 * Parses and prints the Analytics Reporting API V4 response.
	 *
	 * @param An Analytics Reporting API V4 response.
	 */
	public static function printResults($reports) {
	  for ( $reportIndex = 0; $reportIndex < count( $reports ); $reportIndex++ ) {
		$report = $reports[ $reportIndex ];
		$header = $report->getColumnHeader();
		$dimensionHeaders = $header->getDimensions();
		$metricHeaders = $header->getMetricHeader()->getMetricHeaderEntries();
		$rows = $report->getData()->getRows();

		for ( $rowIndex = 0; $rowIndex < count($rows); $rowIndex++) {
		  $row = $rows[ $rowIndex ];
		  $dimensions = $row->getDimensions();
		  $metrics = $row->getMetrics();
		  for ($i = 0; $i < count($dimensionHeaders) && $i < count($dimensions); $i++) {
			print($dimensionHeaders[$i] . ": " . $dimensions[$i] . "\n");
		  }

		  for ($j = 0; $j < count($metrics); $j++) {
			$values = $metrics[$j]->getValues();
			for ($k = 0; $k < count($values); $k++) {
			  $entry = $metricHeaders[$k];
			  print($entry->getName() . ": " . $values[$k] . "\n");
			}
		  }
		}
	  }
	}
	
/**
	 * KL repurpose - Parses and returns a simplified result array from the Analytics Reporting API V4 response.
	 *
	 * @param An Analytics Reporting API V4 response.
	 */
	public static function getResults($reports, $KLfilter = true /* KL-specific fixes */) {
	  $return = array();
	  $index = 0;
	  
	  //echo '<pre>'; var_dump($reports); echo '</pre>';
		
	  for ( $reportIndex = 0; $reportIndex < count( $reports ); $reportIndex++ ) {		  
		$report = $reports[ $reportIndex ];
		$header = $report->getColumnHeader();
		$dimensionHeaders = $header->getDimensions();
		$metricHeaders = $header->getMetricHeader()->getMetricHeaderEntries();
		$rows = $report->getData()->getRows();

		for ( $rowIndex = 0; $rowIndex < count($rows); $rowIndex++) {
		  $row = $rows[ $rowIndex ];
		  $dimensions = $row->getDimensions();		  
		  $metrics = $row->getMetrics();
		  for ($i = 0; $i < count($dimensionHeaders) && $i < count($dimensions); $i++) {			  
			//print($dimensionHeaders[$i] . ": " . $dimensions[$i] . "\n");
			$return[$index]['dimension'] = $dimensions[$i];
		  }

		  for ($j = 0; $j < count($metrics); $j++) {
			$values = $metrics[$j]->getValues();
			for ($k = 0; $k < count($values); $k++) {
			  $entry = $metricHeaders[$k];
			  //print($entry->getName() . ": " . $values[$k] . "\n");			  
			  $return[$index]['name'] = $entry->getName();
			  $return[$index]['values'] = $values[$k];
			}
		  }
		  
		  $index++;
		}
	  }
	  
	  if ($KLfilter) {
		  $return = self::KLfilter($return);
	  }
	  
	  return $return;
	}	
	
	/* KL use */
	public static $ganalytics = null; // GA service object
	
	/* Optional KL-specific fixes for result values */
	public static function KLfilter($results) {		
		for ($c = 0; $c < count($results); $c++) {
			if ($results[$c]['name'] == "avgSessionDuration") {			
				$results[$c]['values'] = gmdate("H:i:s",$results[$c]['values']); // seconds -> H:i:s
			}
		}
		return $results;
	}
	
	/* helpers */
	public static function isComplexAnalytic($results) {
		return count($results) > 1; // || isset($result['dimension'])
	}
	public static function isSimpleAnalytic($results) {
		return !self::isComplexAnalytic($results);
	}
	/* return rudimentary html for complex analytics results table */
	public static function htmlTable($results) {		
		$html = '<table>';
		$html .= '<thead>';
		$html .= '<tr>';
		$html .= '<th>'.'value'.'</th>'; // todo
		$html .= '<th>'.$results[0]['name'].'</th>';
		$html .= '</tr>';
		$html .= '</thead>';
		
		$html .= '<tbody>';
		foreach ($results as $result) {
			//echo $result['dimension'].': '.$result['values'].' '.$result['name'];
			$html .= '<tr>';
			$html .= '<td>'.$result['dimension'].'</td>';
			$html .= '<td>'.$result['values'].'</td>';
			$html .= '</tr>';
		}
		$html .= '<tbody>';
		$html .= '</table>';
		
		return $html;
	}
		
	/* main convenience function */
	public static function getGA($start, $end, $pageSize, $metric, $dimension = null) {
		if (!self::$ganalytics) { self::$ganalytics = self::initializeAnalytics(); }
		$response = self::getReport(self::$ganalytics, $start, $end, $pageSize, $metric, $dimension);		
		return $response;
	}
	
	/* basic test of status */
	public static function test() {
		if (!self::$ganalytics) { self::$ganalytics = self::initializeAnalytics(); }		
		return self::$ganalytics && get_class(self::$ganalytics) == "Google_Service_AnalyticsReporting";
	}
	
	/* basic sample of KLGA metrics via shortcode */
	public static function klgas($atts, $content = null) {		
		/* Ref: https://developers.google.com/analytics/devguides/reporting/core/dimsmets#cats=user,session */		
		/* Ref: https://gist.github.com/denisyukphp/6fcb94a5582f333a16f9d53e4f278168 */
		
		// Process filter requests: validate
		if (isset($_POST['klga_start'])) {
			if (preg_match("/\d{4}-\d{2}-\d{2}/", $_POST['klga_start']) !== 1) {
				echo '<p>Invalid submission.</p>';
				$_POST['klga_start'] = null;
			}
		}
		if (isset($_POST['klga_end'])) {
			if (preg_match("/\d{4}-\d{2}-\d{2}/", $_POST['klga_end']) !== 1) {
				echo '<p>Invalid submission.</p>';
				$_POST['klga_end'] = null;
			}
		}
		if (isset($_POST['klga_start']) && isset($_POST['klga_end'])) {
			if ($_POST['klga_start'] > $_POST['klga_end']) {
				echo '<p>Start date after end date.</p>';
				$_POST['klga_start'] = null;
				$_POST['klga_end'] = null;
			}
		}
		
		// Show date and limit filters
		// Set $_POSTs to current or default values
		$_POST['klga_limit'] = isset($_POST['klga_limit'])?$_POST['klga_limit']:20;		
		$today = getdate();
		if (!isset($_POST['klga_start'])) {
			$_POST['klga_start'] = $today['year'].'-'.sprintf("%02d",$today['mon']).'-'.'01';
			$_POST['klga_end'] = null;
		}
		if (!isset($_POST['klga_end'])) {
			$_POST['klga_end'] = $today['year'].'-'.sprintf("%02d",$today['mon']).'-'.sprintf("%02d",$today['mday']);
		}
				
		echo '<form method = "post">';
		echo 'Start: ';
		echo '<input type = "text" name = "klga_start" value = "'.$_POST['klga_start'].'" size = "12" />';
		echo ' End: ';
		echo '<input type = "text" name = "klga_end" value = "'.$_POST['klga_end'].'" size = "12" />';
		echo ' Limit: ';
		echo '<input type = "number" name = "klga_limit" value = "'.$_POST['klga_limit'].'" size = "3" />';
		echo '&nbsp;';
		echo '<input type = "submit" name = "klga_submit" value = "submit" />';
		echo '</form>';
				
		$analytics = array(
			array ('metric' => "visits"), // || "sessions"
			array ('metric' => "users"),
			array ('metric' => "pageviews"),
			array ('metric' => "avgSessionDuration"), /* in seconds, converts to H:i:s */
			array ('metric' => "bounceRate"),
			array ('metric' => "pageviewsPerSession"), 
			
			array ('metric' => "pageviews", 'dimension' => 'source'), // 'dimension' => 'fullReferrer' gives full referrer URL 
			array ('metric' => "pageviews", 'dimension' => 'country'),
			array ('metric' => "pageviews", 'dimension' => 'keyword'),
			array ('metric' => "pageviews", 'dimension' => 'pagePath'),
		);
		
		// loop thorugh all analytics, get using getGA() and display	
		foreach ($analytics as $analytic) {
			$response = self::getGA($_POST['klga_start'], $_POST['klga_end'],(int) $_POST['klga_limit'],$analytic['metric'],isset($analytic['dimension'])?$analytic['dimension']:null);
			echo '<h4>';
			echo $analytic['metric'];
			if (isset($analytic['dimension'])) { echo ' x '.$analytic['dimension']; }
			echo ':</h4>';
			//self::printResults($response);
			// parse simplified result array from response
			$results = self::getResults($response);
			
			if (self::isSimpleAnalytic($results)) {
				echo $results[0]['values'];
				echo '<br/>';
			} else {								
				/*
				foreach ($results as $result) {
					echo $result['dimension'].': '.$result['values'].' '.$result['name'];
					echo '<br/>';
				}
				*/
				echo self::htmlTable($results);
			}
			echo '<br/>';			
		}
		
	}
		
}

add_shortcode('klgas','KLGA::klgas');
