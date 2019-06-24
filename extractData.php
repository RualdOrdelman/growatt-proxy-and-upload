<?php

        require("./include/phpMQTT.php");
	class Growatt {
		public $mqtt_client_id = "Growatt_Inverter"; // make sure this is unique for connecting to sever - you could use uniqid()
		public $mqtt_connection;
		public $lastEToday = 0;
		public $lastEToday_currentfilename = '';
		public $pvoutput_current_buffer_filename = '';
		public $inverter_Inputfilename = '';
		public $mqtt_current_inverter_client_id = '';
		public $pvOutputSid_current = '';
		public $config;

		public function __construct() 
		{
		 	($this->config = yaml_parse_file("settings.yaml")) || die("YAML file not found");
		}

		public function run() {
			//Achterhalen welke input omvormer file vanuit Proxy binnenkomt. Op basis daarvan variabelen 
			//zetten die nodig zijn om het bericht juist te verwerken.
			if (is_file($this->config['Inverter1_output_filename'])) 
			{
				echo "Inputfile gevonden: " . $this->config['Inverter1_output_filename'] . "\n";
				$this->lastEToday_currentfilename = $this->config['Inverter1_last_etoday_filename'];
				$this->pvoutput_current_buffer_filename = $this->config['Inverter1_pvoutput_buffer_filename'];
				$this->mqtt_current_inverter_client_id = $this->config['Inverter1_mqtt_client_ID'];
				$this->inverter_Inputfilename = $this->config['Inverter1_output_filename'];
				$this->pvOutputSid_current = $this->config['Inverter1_PVoutput_ID'];
				$result = $this->parseInputMessage(file_get_contents($this->config['Inverter1_output_filename']));
			}
			else if (is_file($this->config['Inverter2_output_filename'])) 
			{
				echo "Inputfile gevonden: " . $this->config['Inverter2_output_filename'] . "\n";
				$this->lastEToday_currentfilename = $this->config['Inverter2_last_etoday_filename'];
				$this->pvoutput_current_buffer_filename = $this->config['Inverter2_pvoutput_buffer_filename'];
				$this->mqtt_current_inverter_client_id = $this->config['Inverter2_mqtt_client_ID'];
				$this->inverter_Inputfilename = $this->config['Inverter2_output_filename'];
				$this->pvOutputSid_current = $this->config['Inverter2_PVoutput_ID'];
				$result = $this->parseInputMessage(file_get_contents($this->config['Inverter2_output_filename']));
			}
			else if (is_file($this->config['Inverter3_output_filename'])) 
			{
                                echo "Inputfile gevonden: " . $this->config['Inverter3_output_filename'] . "\n";
                                $this->lastEToday_currentfilename = $this->config['Inverter3_last_etoday_filename'];
                                $this->pvoutput_current_buffer_filename = $this->config['Inverter3_pvoutput_buffer_filename'];
                                $this->mqtt_current_inverter_client_id = $this->config['Inverter3_mqtt_client_ID'];
                                $this->inverter_Inputfilename = $this->config['Inverter3_output_filename'];
                                $this->pvOutputSid_current = $this->config['Inverter3_PVoutput_ID'];
                                $result = $this->parseInputMessage(file_get_contents($this->config['Inverter3_output_filename']));
			}
			else
			{
				echo "No inputfile found\n"; 
				exit;
			}
			$this->lastEToday = file_get_contents($this->lastEToday_currentfilename);

			var_dump($result);
			$this->uploadDataPvoutput($result);
			$this->uploadToMQTT($result);
			unlink($this->inverter_Inputfilename); //gooi na het verwerken het buffer bestand weg
			file_put_contents($this->lastEToday_currentfilename, $result->E_Today); //schrijf laatste dagtotaal weg in dagteller
		}

		//functie om het binnengekomen 01 04 bericht te ontleden in losse values. 
		private function parseInputMessage($inputmsg)
		{
			echo "In parseInputMessage \n";
			$testBin = $inputmsg;
			//$result = "";
			if ($testBin[6] == hex2bin("01") && $testBin[7] == hex2bin("04") ) 
			{
				$result = $this->decryptMsg($testBin);
				$msg = $result;
				$start = 0;
				$result = (object) [];
				$length = 10;
				$result->deviceId = $this->getValue($msg, $start, $length);
				$start += $length;

				$length = 10;
				$result->inverterId = $this->getValue($msg, $start, $length);
				$start += $length;

				$length = 5;
				$result->empty = $this->getValue($msg, $start, $length);
				$start += $length;

				$length = 6;
				$result->gwVersion = $this->getValue($msg, $start, $length);
				$start += $length;

				$length = 2;
				$result->invStat = $this->getSumValue($msg, $start, $length);
				$start += $length;

				$length = 4;
				$result->Ppv = ($this->getSumValue($msg, $start, $length))/10;
				$start += $length;

				$length = 2;
				$result->Vpv1 = ($this->getSumValue($msg, $start, $length))/10;
				$start += $length;

				$length = 2;
				$result->Ipv1 = ($this->getSumValue($msg, $start, $length))/10;
				$start += $length;

				$length = 4;
				$result->Ppv1 = ($this->getSumValue($msg, $start, $length))/10;
				$start += $length;

				$length = 2;
				$result->Vpv2 = ($this->getSumValue($msg, $start, $length))/10;
				$start += $length;

				$length = 2;
				$result->Ipv2 = ($this->getSumValue($msg, $start, $length))/10;
				$start += $length;

				$length = 4;
				$result->Ppv2 = ($this->getSumValue($msg, $start, $length))/10;
				$start += $length;

				$length = 4;
				$result->Pac = ($this->getSumValue($msg, $start, $length));
				$start += $length;

				$length = 2;
				$result->Fac = ($this->getSumValue($msg, $start, $length))/100;
				$start += $length;

				$length = 2;
				$result->Vac1 = ($this->getSumValue($msg, $start, $length));
				$start += $length;

				$length = 2;
				$result->Iac1 = ($this->getSumValue($msg, $start, $length));
				$start += $length;

				$length = 4;
				$result->Pac1 = ($this->getSumValue($msg, $start, $length));
				$start += $length;

				$length = 2;
				$result->Vac2 = ($this->getSumValue($msg, $start, $length));
				$start += $length;

				$length = 2;
				$result->Iac2 = ($this->getSumValue($msg, $start, $length));
				$start += $length;

				$length = 4;
				$result->Pac2 = ($this->getSumValue($msg, $start, $length));
				$start += $length;

				$length = 2;
				$result->Vac3 = ($this->getSumValue($msg, $start, $length));
				$start += $length;

				$length = 2;
				$result->Iac3 = ($this->getSumValue($msg, $start, $length));
				$start += $length;

				$length = 4;
				$result->Pac3 = ($this->getSumValue($msg, $start, $length));
				$start += $length;

				$length = 4;
				$result->E_Today = ($this->getSumValue($msg, $start, $length))/10;
				$start += $length;

				$length = 4;
				$result->E_Total = ($this->getSumValue($msg, $start, $length))/10;
				$start += $length;

				$length = 4;
				$result->Tall = ($this->getSumValue($msg, $start, $length))/(60*60*2);
				$start += $length;

				$length = 2;
				$result->Tmp = ($this->getSumValue($msg, $start, $length));
				$start += $length;

				$length = 2;
				$result->Isof = ($this->getSumValue($msg, $start, $length));
				$start += $length;

				$length = 2;
				$result->GFCIF = ($this->getSumValue($msg, $start, $length)) /10;
				$start += $length;

				$length = 2;
				$result->DCIF = ($this->getSumValue($msg, $start, $length)) /10;
				$start += $length;

				$length = 2;
				$result->Vpvfault = ($this->getSumValue($msg, $start, $length));
				$start += $length;

				$length = 2;
				$result->Vacfault = ($this->getSumValue($msg, $start, $length));
				$start += $length;

				$length = 2;
				$result->Isof = ($this->getSumValue($msg, $start, $length));
				$start += $length;

				$length = 2;
				$result->Facfault = ($this->getSumValue($msg, $start, $length))/100;
				$start += $length;

				$length = 2;
				$result->Tmpfault = ($this->getSumValue($msg, $start, $length));
				$start += $length;

				$length = 2;
				$result->Faultcode = ($this->getSumValue($msg, $start, $length));
				$start += $length;

				$length = 2;
				$result->IPMtemp = ($this->getSumValue($msg, $start, $length));
				$start += $length;

				$length = 2;
				$result->Pbusvolt = ($this->getSumValue($msg, $start, $length));
				$start += $length;

				$length = 2;
				$result->Nbusvolt = ($this->getSumValue($msg, $start, $length));
				$start += $length;

				$start += 12;

				$length = 4;
				$result->Epv1today = ($this->getSumValue($msg, $start, $length)) / 10;
				$start += $length;

				$length = 4;
				$result->Epv1total = ($this->getSumValue($msg, $start, $length)) / 10;
				$start += $length;

				$length = 4;
				$result->Epv2today = ($this->getSumValue($msg, $start, $length)) / 10;
				$start += $length;

				$length = 4;
				$result->Epv2total = ($this->getSumValue($msg, $start, $length)) / 10;
				$start += $length;

				$length = 4;
				$result->Epvtotal = ($this->getSumValue($msg, $start, $length)) / 10;
				$start += $length;

				$length = 4;
				$result->ERac = ($this->getSumValue($msg, $start, $length)) * 100;
				$start += $length;

				$length = 4;
				$result->ERactoday = ($this->getSumValue($msg, $start, $length)) * 100;
				$start += $length;

				$length = 4;
				$result->ERactotal = ($this->getSumValue($msg, $start, $length)) * 100;
				$start += $length;
			}
			return $result;	
		}
	
		//Decrypt het binnengekomen bericht met XOR en een string value. 
		private function decryptMsg($msg)
		{
			$msg = substr($msg, 8);
			$encryptionKey = "Growatt";
			$pos = 0;
			for ($i = 0; $i < strlen($msg); $i++) {
			    $value = ord($encryptionKey[$pos++]);
		//            var_dump($i);
		//            var_dump($value);
		//            var_dump(ord($msg[$i]));
			    if ($pos +1 > strlen($encryptionKey)) $pos = 0;
			    $workValue = ord($msg[$i]) ^ $value;
		//            var_dump($workValue);
			    if ($workValue < 0) $workValue += 256;
		//            var_dump($workValue);
			    $msg[$i] = chr($workValue);
		//            var_dump($msg[$i]);
		//            echo "<br>";
			}
		//        $device = substr($msg, 0, 10);
			return $msg;
		}

		private function getValue($msg, $start, $length) 
		{
			$result = "";
			for ($i = 0; $i < $length; $i++) {
			    $result .= $msg[($start+$i)];
			}
			return $result;
		}

		private function getSumValue($msg, $start, $length) 
		{
			$result = 0;
			for ($i = 0; $i < $length; $i++) {
			    $result *= 256;
			    $result += ord($msg[($start+$i)]);
			}
			return $result;
		}

		private function uploadToMQTT($data)
		{
			echo "In uploadToMQTT \n";
			$this->mqtt_connection = new phpMQTT($this->config['MQTT_server_IP'], $this->config['MQTT_server_port'], $this->mqtt_client_id);
			if ($this->mqtt_connection->connect(true, NULL, $this->config['MQTT_server_username'], $this->config['MQTT_server_password']))
			{			
				//"PV_Inverter/schuindak_oost/EToday"
				$this->mqtt_connection->publish("PV_Inverter/" . $this->mqtt_current_inverter_client_id . "/EToday", $data->E_Today, 0);
				$this->mqtt_connection->publish("PV_Inverter/" . $this->mqtt_current_inverter_client_id . "/ETotal", $data->E_Total, 0);
				$this->mqtt_connection->publish("PV_Inverter/" . $this->mqtt_current_inverter_client_id . "/Ppv", $data->Ppv, 0);
				$this->mqtt_connection->publish("PV_Inverter/" . $this->mqtt_current_inverter_client_id . "/Ppv1", $data->Ppv1, 0);
				$this->mqtt_connection->publish("PV_Inverter/" . $this->mqtt_current_inverter_client_id . "/Ppv2", $data->Ppv2, 0);
                                $this->mqtt_connection->publish("PV_Inverter/" . $this->mqtt_current_inverter_client_id . "/Vpv1", $data->Vpv1, 0);
                                $this->mqtt_connection->publish("PV_Inverter/" . $this->mqtt_current_inverter_client_id . "/Vpv2", $data->Vpv2, 0);
                                $this->mqtt_connection->publish("PV_Inverter/" . $this->mqtt_current_inverter_client_id . "/Ipv1", $data->Ipv1, 0);
                                $this->mqtt_connection->publish("PV_Inverter/" . $this->mqtt_current_inverter_client_id . "/Ipv2", $data->Ipv2, 0);
				$this->mqtt_connection->publish("PV_Inverter/" . $this->mqtt_current_inverter_client_id . "/IPMTemp", $data->IPMtemp/100, 0);
				echo "Post to MQTT topic: " . "PV_Inverter/" . $this->mqtt_current_inverter_client_id . "/Ppv\n"; 
				$this->mqtt_connection->close();
			}
			else
			{
				echo "Time out!\n";
			}
		}

		private function uploadDataPvoutput($data) 
		{
			echo "Start uploadDataPvoutput\n";
			//echo "0.1\n";
			$ch = curl_init();
			$timestamp = time()."000000000\n";
			$uploadData = [];
			//echo "0.2\n";
			$uploadData['d'] = strftime("%Y%m%d");
			$uploadData['t'] = strftime("%H:%M");

			//echo "$this->lastEToday: " . $this->lastEToday . "\n";
			//echo "$data->E_Today: ", $data->E_Today . "\n";
			if ($this->lastEToday < (0+$data->E_Today)) {
				$uploadData['v1'] = $data->E_Today * 1000;
			}
			//echo "1\n";
			//echo "Ppv: " .  $data->Ppv . "\n";
			$uploadData['v2'] = $data->Ppv;
			// set url 
			//echo "1.1\n";
			if (!empty($uploadData['v1']))
				$data = $uploadData['d'].','.$uploadData['t'].','.$uploadData['v1'].','.$uploadData['v2'];
			else
				$data = $uploadData['d'].','.$uploadData['t'].',-1,'.$uploadData['v2'];
			//echo "1.2\n";
			$buffer = file_get_contents($this->pvoutput_current_buffer_filename);
			$buffer = implode(';', [$buffer, $data]);
			//echo "1.3\n";
			if (substr($buffer, 0, 1) == ';') $buffer = ltrim($buffer, ';');
				file_put_contents($this->pvoutput_current_buffer_filename, $buffer);
			//echo "1.4\n";
			if (substr_count($buffer, ';') > 6) 
			{
				if (substr_count($buffer, ';') > 30) 
				{
					$buffers = explode(';', $buffer);
					$buffer = "";
					for ($i=0; $i < 30; $i ++) {
						$buffer .= ($i > 0 ? ";" : "").$buffers[$i];
					}
					$succesBuffer = "";
					for ($i=30; $i <= substr_count($buffer, ';'); $i ++) {
						$succesBuffer .= ($i > 0 ? ";" : "").$buffers[$i];
					}
				}
				//echo "2\n";
				$uploadData = ['data'=>$buffer];
				//curl_setopt($ch, CURLOPT_URL, "https://pvoutput.org/service/r2/addstatus.jsp?".http_build_query($uploadData));
				curl_setopt($ch, CURLOPT_URL, "https://pvoutput.org/service/r2/addbatchstatus.jsp?".http_build_query($uploadData));

				curl_setopt($ch, CURLOPT_HTTPHEADER, array(
					'X-Pvoutput-Apikey: '.$this->config['PVoutput_API_KEY'],
					'X-Pvoutput-SystemId: '.$this->pvOutputSid_current
					//'X-Pvoutput-SystemId: '.$this->pvOutputSid_inverter1
				));
				//echo "3\n";	
				//return the transfer as a string
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
				curl_setopt($ch, CURLOPT_TIMEOUT, 4);

				//echo "4\n";
				// $output contains the output string
				$output = curl_exec($ch);

				//echo "5\n";
				echo "Output:" . $output . "\n";
				if (curl_getinfo($ch, CURLINFO_HTTP_CODE) == 200)
					file_put_contents($this->pvoutput_current_buffer_filename, $succesBuffer);
				// close curl resource to free up system resources
				curl_close($ch);
			    }
		}
	}
	//($config = yaml_parse_file("settings.yaml")) || die("YAML file not found");
	$Growatt = new Growatt();
	//$Growatt->config = (yaml_parse_file("settings.yaml")) || die("YAML file not found");
	$Growatt->run();
?>

