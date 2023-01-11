<?php
class EseraGaszaehler extends IPSModule 
{
    public function Create()
	{
        //Never delete this line!
        parent::Create();
        //These lines are parsed on Symcon Startup or Instance creation 
        //You cannot use variables here. Just static values.
        $this->RegisterPropertyInteger("CounterID", 0);
        $this->RegisterPropertyInteger("Impulses", 1000);
        $this->RegisterPropertyFloat("Zustandszahl", 0.9692);
        $this->RegisterPropertyFloat("Brennwert", 11.293);
		
		$this->RegisterPropertyFloat("Centkwh", 0.1066);
	    
		$this->RegisterVariableInteger("HourResetTime", "Stunden Reset Time", "~UnixTimestamp", 0);
		$this->RegisterVariableInteger("DailyResetTime", "Tages Reset Time", "~UnixTimestamp", 1);
	    $this->RegisterVariableInteger("MonthlyResetTime", "Monats Reset Time", "~UnixTimestamp", 2);
		$this->RegisterVariableInteger("YearlyResetTime", "Jahres Reset Time", "~UnixTimestamp", 3);
		
		$this->RegisterVariableInteger("Counter", "Counter", "", 10);
	    $this->RegisterVariableFloat("Zaehlerstand", "Zaehlerstand", "~Gas", 11);
	    $this->RegisterVariableFloat("Zaehlerstandalt", "Zaehlerstand alt", "~Gas", 12
		$this->RegisterVariableFloat("Verbrauch", "Verbrauch", "~Gas", 13);	    
		
		$this->RegisterVariableInteger("StdCounter", "Counter Stunde", "", 15);
		$this->RegisterVariableFloat("VerbrauchStdm", "Verbrauch in der Stunde in m³", "~Gas", 16);
		$this->RegisterVariableFloat("VerbrauchStdkwh", "Verbrauch in der Stunde in kwh", "Kirsch.kWh", 17); 

		$this->RegisterVariableInteger("TagCounter", "Counter Tag", "", 20);
		$this->RegisterVariableFloat("VerbrauchTagm", "Verbrauch am Tag in m³", "~Gas", 21);
		$this->RegisterVariableFloat("VerbrauchTagkwh", "Verbrauch am Tag in kwh", "Kirsch.kWh", 22);
	    $this->RegisterVariableFloat("VerbrauchTagEuro", "Verbrauch am Tag in Euro", "~Euro", 23);

	    
		$this->RegisterVariableInteger("MonatCounter", "Counter Monat", "", 30);
        $this->RegisterVariableFloat("VerbrauchMonatm", "Verbrauch im Monat in m³", "~Gas", 31);
        $this->RegisterVariableFloat("VerbrauchMonatkwh", "Verbrauch im Monat in kwh", "Kirsch.kWh", 32);

		$this->RegisterVariableInteger("JahrCounter", "Counter Jahr", "", 40);
        $this->RegisterVariableFloat("VerbrauchJahrm", "Verbrauch im Jahr in m³", "~Gas", 41);
        $this->RegisterVariableFloat("VerbrauchJahrkwh", "Verbrauch im Jahr in kwh", "Kirsch.kWh", 42);

	    $ArchiveHandlerID = IPS_GetInstanceListByModuleID('{43192F0B-135B-4CE7-A0A7-1475603F3060}');
	    AC_SetLoggingStatus($ArchiveHandlerID[0], $this->GetIDForIdent("Counter"), true);
	    AC_SetLoggingStatus($ArchiveHandlerID[0], $this->GetIDForIdent("Verbrauch"), true);
		AC_SetLoggingStatus($ArchiveHandlerID[0], $this->GetIDForIdent("Zaehlerstand"), true);
	    AC_SetLoggingStatus($ArchiveHandlerID[0], $this->GetIDForIdent("StdCounter"), true);
	    AC_SetLoggingStatus($ArchiveHandlerID[0], $this->GetIDForIdent("VerbrauchStdm"), true);
		AC_SetLoggingStatus($ArchiveHandlerID[0], $this->GetIDForIdent("VerbrauchStdkwh"), true);
		
		$this->RegisterTimer("Refresh", 0, 'ESERA_RefreshCounterG($_IPS[\'TARGET\']);');
		$this->RegisterTimer("HourReset", 0, 'ESERA_ResetPowerMeterHour($_IPS[\'TARGET\']);');
		$this->RegisterTimer("DailyReset", 0, 'ESERA_ResetPowerMeterDaily($_IPS[\'TARGET\']);');
		$this->RegisterTimer("MonthlyReset", 0, 'ESERA_ResetPowerMeterMonthly($_IPS[\'TARGET\']);');
        $this->RegisterTimer("YearlyReset", 0, 'ESERA_ResetPowerMeterYearly($_IPS[\'TARGET\']);');
	}
	
    public function Destroy()
	{
        //Never delete this line!
        parent::Destroy();
    }
	
    public function ApplyChanges()
	{
        //Never delete this line!
        parent::ApplyChanges();

        $this->SetTimerInterval("Refresh", 60 * 1000);
		$this->SetHourTimerInterval();
        $this->SetDailyTimerInterval();
        $this->SetMonthlyTimerInterval();
        $this->SetYearlyTimerInterval();    
    }

	public function ReceiveData($JSONString) 
	{
        // not implemented   
    }
	
		public function ResetPowerMeterHour()
	{
		$this->SetHourTimerInterval();
		$Centkwh = $this->ReadPropertyFloat("Centkwh");	
		SetValue($this->GetIDForIdent("StdCounter"), 0);
        SetValue($this->GetIDForIdent("VerbrauchStdm"), 0);
		SetValue($this->GetIDForIdent("VerbrauchStdkwh"), 0);
	}
	
	public function ResetPowerMeterDaily()
	{
        $this->SetDailyTimerInterval();
		$this->SetMonthlyTimerInterval();
		$this->SetYearlyTimerInterval();
        $Centkwh = $this->ReadPropertyFloat("Centkwh");
		SetValue($this->GetIDForIdent("TagCounter"), 0);
        SetValue($this->GetIDForIdent("VerbrauchTagm"), 0);
		SetValue($this->GetIDForIdent("VerbrauchTagkwh"), 0);
    }
	
	public function ResetPowerMeterMonthly()
	{
		$Centkwh = $this->ReadPropertyFloat("Centkwh");
        SetValue($this->GetIDForIdent("MonatCounter"), 0);
        SetValue($this->GetIDForIdent("VerbrauchMonatm"), 0);
		SetValue($this->GetIDForIdent("VerbrauchMonatkwh"), 0);
    }
	
    public function ResetPowerMeterYearly()
	{
        SetValue($this->GetIDForIdent("JahrCounter"), 0);
        SetValue($this->GetIDForIdent("VerbrauchJahrm"), 0);
		SetValue($this->GetIDForIdent("VerbrauchJahrkwh"), 0);
    }
	
	public function RefreshCounterG()
	{
       $this->calculate();   
    }
	
	private function Calculate()
	{
		global $delta;
		global $factor;
		global $delta_qm;
		global $CounterNew;
		// Jahresgrenzwert
        $Zustandszahl = $this->ReadPropertyFloat("Zustandszahl");
		$Brennwert = $this->ReadPropertyFloat("Brennwert");
		$Centkwh = $this->ReadPropertyFloat("Centkwh");
		$CounterOld = GetValue($this->GetIDForIdent("Counter"));
		$CounterNew = GetValue($this->ReadPropertyInteger("CounterID"));

		if($CounterOld == 0)
		{
			SetValue($this->GetIDForIdent("Counter"), $CounterNew);
		}
		Else
		{

			
			$delta = $CounterNew - $CounterOld;
			$Factor = $this->GetFactor($this->ReadPropertyInteger("Impulses"));
			$delta_qm = ($delta * $Factor);
	
			SetValue($this->GetIDForIdent("Counter"), $CounterNew);
			//SetValue($this->GetIDForIdent("Verbrauch"), $delta_qm);
			$ZaehlerOld = GetValue($this->GetIDForIdent("Zaehlerstand"));
			SetValue($this->GetIDForIdent("Zaehlerstand"), $ZaehlerOld + $delta_qm);
			$Zaehlerstan = GetValue($this->GetIDForIdent("Zaehlerstand"):
			$Zaehlerstandold = GetValue($this->GetIDForIdent("Zaehlerstandalt"):
			if ($Zaehlerstandold > 0 )
			{
				SetValue($this->GetIDForIdent("Verbrauch"), $Zaehlerstand - $Zaehlerstandold);
			}
		}
		
		//Counter Std
		$CounterStd = GetValue($this->GetIDForIdent("StdCounter")) + $delta;
        SetValue($this->GetIDForIdent("StdCounter"), $CounterStd);
        SetValue($this->GetIDForIdent("VerbrauchStdm"), $CounterStd * $Factor);
		SetValue($this->GetIDForIdent("VerbrauchStdkwh"), $CounterStd * $Factor * $Zustandszahl * $Brennwert);
		
		//Counter Tag
		$CounterTag = GetValue($this->GetIDForIdent("TagCounter")) + $delta;
        SetValue($this->GetIDForIdent("TagCounter"), $CounterTag);
        SetValue($this->GetIDForIdent("VerbrauchTagm"), $CounterTag * $Factor);
		//$Zustandszahl = 0.9692;
		//$Brennwert = 11.293;
		//$FactorKWh = 0.9692*11.293;
		SetValue($this->GetIDForIdent("VerbrauchTagkwh"), $CounterTag * $Factor * $Zustandszahl * $Brennwert);
		$ID1 = $this->GetIDForIdent("VerbrauchTagkwh");
		SetValue($this->GetIDForIdent("VerbrauchTagEuro"), GetValue($ID1) * 0.1066);
		//echo "Zustandszahl = $AnnualLimit \r\n";

		// Counter Monat  
        $CounterMonat = GetValue($this->GetIDForIdent("MonatCounter")) + $delta;
        SetValue($this->GetIDForIdent("MonatCounter"), $CounterMonat);
        SetValue($this->GetIDForIdent("VerbrauchMonatm"), $CounterMonat * $Factor);
		SetValue($this->GetIDForIdent("VerbrauchMonatkwh"), $CounterMonat * $Factor * $Zustandszahl * $Brennwert);
		
		// Counter Jahr  
        $CounterJahr = GetValue($this->GetIDForIdent("JahrCounter")) + $delta;
        SetValue($this->GetIDForIdent("JahrCounter"), $CounterJahr);
        SetValue($this->GetIDForIdent("VerbrauchJahrm"), $CounterJahr * $Factor);
		SetValue($this->GetIDForIdent("VerbrauchJahrkwh"), $CounterJahr * $Factor * $Zustandszahl * $Brennwert);

	}
	
	private function DebugMessage($Sender, $Message)
	{
        $this->SendDebug($Sender, $Message, 0);
    }
	
	private function GetFactor($Impulses)
	{
        switch ($Impulses){
            case 250:
              return (0.04);
            break;
              
            case 500:
              return (0.00);
            break;
              
            case 800:
              return (0.0125);
            break;
              
            case 1000:
              return (0.01);
            break;
              
            case 2000:
              return (0.005);
            break;
        }    
    }
	
		protected function SetHourTimerInterval()
	{
    	$Now = new DateTime(); 
		$Target = new DateTime(); 
		//$Target->modify('+1 hour'); 
		$stunde =  Date('H');
		$stunde++; 
		$Target->setTime($stunde,0,1); 
		$Diff =  $Target->getTimestamp() - $Now->getTimestamp(); 
		$Tar = $Target->getTimestamp();
		$Interval = $Diff * 1000;  
	   	$this->SetTimerInterval("HourReset", $Interval);
		SetValue($this->GetIDForIdent("HourResetTime"), $Tar);
	}
		
	protected function SetDailyTimerInterval()
	{
    	$Now = new DateTime(); 
		$Target = new DateTime(); 
		$Target->modify('+1 day'); 
		$Target->setTime(0,0,1); 
		$Diff =  $Target->getTimestamp() - $Now->getTimestamp(); 
		$Tar = $Target->getTimestamp();
		$Interval = $Diff * 1000;  
	   	$this->SetTimerInterval("DailyReset", $Interval);
		SetValue($this->GetIDForIdent("DailyResetTime"), $Tar);
    }
	protected function SetMonthlyTimerInterval()
	{
        $Now = new DateTime(); 
		$Target = new DateTime(); 
		$Target->modify('first day of next month');
		$Target->setTime(0,0,5); 
		$Diff =  $Target->getTimestamp() - $Now->getTimestamp(); 
		$Interval = $Diff * 1000;  
		$this->SetTimerInterval("MonthlyReset", $Interval);
		SetValue($this->GetIDForIdent("MonthlyResetTime"), $Target->getTimestamp());
    }
    protected function SetYearlyTimerInterval()
	{
        $Now = new DateTime(); 
		$Target = new DateTime(); 
		$Target->modify('1st January Next Year');
		$Target->setTime(0,0,10); 
		$Diff = $Target->getTimestamp() - $Now->getTimestamp(); 
		$Interval = $Diff * 1000;  
		$this->SetTimerInterval("YearlyReset", $Interval);
		SetValue($this->GetIDForIdent("YearlyResetTime"), $Target->getTimestamp());
    }
}
?>
