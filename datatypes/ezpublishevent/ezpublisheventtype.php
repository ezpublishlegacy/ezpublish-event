<?php

class eZPublishEventType extends eZDataType
{
    const DATA_TYPE_STRING = 'ezpublishevent';
    const DEFAULT_FIELD = 'data_text';

    function eZPublishEventType()
    {
        $this->eZDataType( self::DATA_TYPE_STRING, ezpI18n::tr( 'kernel/classes/datatypes', 'Event', 'Datatype name' ),
                           array( 'serialize_supported' => true ) );
    }

    /*!
     Validates the input and returns true if the input was
     valid for this datatype.
    */
    function validateObjectAttributeHTTPInput( $http, $base, $contentObjectAttribute )
    {
        $now = time();
        if ( $http->hasPostVariable( $base . '_ezpeventdate_data_' . $contentObjectAttribute->attribute( 'id' ) ) )
        {
            $data = $http->postVariable( $base . '_ezpeventdate_data_' . $contentObjectAttribute->attribute( 'id' ) );
            $data_text = array();
            $include = array();
            $exclude = array();
            $version = $contentObjectAttribute->attribute( 'object_version' );
            // get include date
            if( isset( $data['include'] ) )
            {
                foreach( $data['include'] as $key => $includeItem )
                {
                    $validate = array();
                    if( trim( $includeItem['startdate']) != '' )
                    {
                        if( trim( $includeItem['starttime-hour'] ) != '' )
                        {
                            $timeString = trim( $includeItem['startdate'] ) . ' ' . trim( $includeItem['starttime-hour'] );
                            try
                            {
                                $starttime = eZPublishEvent::createDateTime( $timeString, $includeItem, 'start' );
                                $validate = $this->validateDateTime( $now, false, $starttime );
                                if( isset( $validate['state'] ) )
                                {
                                    if( trim( $includeItem['enddate'] ) != '' )
                                    {
                                        $timeString = trim( $includeItem['enddate'] );
                                        if( trim( $includeItem['endtime-hour'] ) != '' )
                                        {
                                            $timeString .= ' ' . trim( $includeItem['endtime-hour'] );
                                        }
                                        else
                                        {
                                            $timeString .= ' 00';
                                        }
                                        $endtime = eZPublishEvent::createDateTime( $timeString, $includeItem, 'end' );
                                        if( $includeItem['startdate'] == $includeItem['enddate'] && ( trim( $includeItem['endtime-hour'] ) == '' || trim( $includeItem['endtime-hour'] ) == '00' ) )
                                        {
                                            $endtime->modify( '+1 day' );
                                        }
                                    }
                                    else
                                    {
                                        $endtime = clone $starttime;
                                        if( trim( $includeItem['endtime-hour'] ) == '00' || trim( $includeItem['endtime-hour'] ) == '' )
                                        {
                                            $endtime->modify( '+1 day' );
                                            $endtime->setTime( 00, 00 );
                                        }
                                        elseif( trim( $includeItem['endtime-hour'] ) != '' && trim( $includeItem['endtime-minute'] ) != '' )
                                        {
                                            $endtime->setTime( trim( $includeItem['endtime-hour'] ), trim( $includeItem['endtime-minute'] ) );
                                        }
                                        elseif( trim( $includeItem['endtime-hour'] ) != '' && trim( $includeItem['endtime-minute'] ) == '' )
                                        {
                                            $endtime->setTime( trim( $includeItem['endtime-hour'] ), 00 );
                                        }
                                    }
                                    $ezpublisheventIni = eZINI::instance( 'ezpublishevent.ini' );
                                    $validate = $this->validateDateTime( $now, $version, $starttime, $endtime, $ezpublisheventIni );
                                    if( isset( $validate['state'] ) )
                                    {
                                        $include[$key] = array( 'start' => $starttime->format( eZPublishEvent::DATE_FORMAT ),
                                                                'end' => $endtime->format( eZPublishEvent::DATE_FORMAT ) );
                                        if( isset( $includeItem['weekdays'] ) && count( $includeItem['weekdays'] ) > 0 && count( $includeItem['weekdays'] ) < 7 )
                                        {
                                            $endtimeCW = clone $endtime;
                                            $starttimeCW = clone $starttime;
                                            $endtimeCW->setTime(00, 00, 00);
                                            $starttimeCW->setTime(00, 00, 00);
                                            $betweenDays = $endtimeCW->diff( $starttimeCW );
                                            if( $betweenDays->format( '%a' ) >= 3 )
                                            {
                                                $weekdayShortNames = $ezpublisheventIni->variable( 'Settings', 'WeekdayShortNames' );
                                                $weekdays = array();
                                                foreach( $includeItem['weekdays'] as $index => $weekday )
                                                {
                                                    $weekdays[] = $weekdayShortNames[$index];
                                                }
                                                $include[$key]['weekdays'] = $weekdays;
                                            }
                                            else
                                            {
                                                unset( $include[$key]['weekdays'] );
                                            }
                                        }
                                    }
                                }
                            }
                            catch ( Exception $e )
                            {
                                $validate['error'] = $e->getMessage();
                            }
                        }
                        else
                        {
                            $validate['error'] = ezpI18n::tr( 'extension/ezpublish-event', 'Set a start time.' );
                        }
                    }
                    else
                    {
                        $validate['error'] = ezpI18n::tr( 'extension/ezpublish-event', 'Select a start date.' );
                    }
                    if( isset( $validate['error'] ) )
                    {
                        $contentObjectAttribute->setValidationError( $validate['error'] );
                        return eZInputValidator::STATE_INVALID;
                    }
                }
            }
            if( isset( $data['exclude'] ) )
            {
                foreach( $data['exclude'] as $key => $excludeItem )
                {
                    $validate = array();
                    if( isset( $excludeItem['startdate'] ) && trim( $excludeItem['startdate'] ) != '' && isset( $excludeItem['enddate'] ) && trim( $excludeItem['enddate'] ) != '' )
                    {
                        $timeString = trim( $excludeItem['startdate'] ) . ' 00';
                        $starttimeExc = eZPublishEvent::createDateTime( $timeString, null, 'start' );
                        $validate = $this->validateDateTime( $now, false, $starttimeExc );
                        if( isset( $validate['state'] ) )
                        {
                            $timeString = trim( $excludeItem['enddate'] ) . ' 00';
                            $endtimeExc = eZPublishEvent::createDateTime( $timeString, null, 'end' );
                            if( !isset( $ezpublisheventIni ) || !$ezpublisheventIni instanceof eZINI )
                                $ezpublisheventIni = eZINI::instance( 'ezpublishevent.ini' );
                            $validate = $this->validateDateTime( $now, $version, $starttimeExc, $endtimeExc, $ezpublisheventIni );
                            if( isset( $validate['state'] ) )
                            {
                                $exclude[$key] = array( 'start' => $starttimeExc->format( eZPublishEvent::DATE_FORMAT ),
                                                        'end' => $endtimeExc->format( eZPublishEvent::DATE_FORMAT ) );
                            }
                        }
                    }
                    elseif( isset( $excludeItem['startdate'] ) && trim( $excludeItem['startdate'] ) != '' && isset( $excludeItem['enddate'] ) && trim( $excludeItem['enddate'] ) == '' )
                    {
                        $validate['error'] = ezpI18n::tr( 'extension/ezpublish-event', 'Select an end date.' );
                    }
                    if( isset( $validate['error'] ) )
                    {
                        $contentObjectAttribute->setValidationError( $validate['error'] );
                        return eZInputValidator::STATE_INVALID;
                    }
                }
            }
            if( isset( $include ) && count( $include ) > 0 )
            {
                ksort( $include );
                $data_array['include'] = $include;
            }
            if( isset( $exclude ) && count( $exclude ) > 0 )
            {
                ksort( $exclude );
                $data_array['exclude'] = $exclude;
            }
            if( count( $data_array ) > 0 )
            {
                $jsonString = json_encode( $data_array );
                $contentObjectAttribute->setAttribute( 'data_text', $jsonString );
            }
        }
        return eZInputValidator::STATE_ACCEPTED;
    }

    /*!
     Fetches the http post var integer input and stores it in the data instance.
    */
    function fetchObjectAttributeHTTPInput( $http, $base, $contentObjectAttribute )
    {
        return true;
    }

    /*!
     Returns the content.
    */
    function objectAttributeContent( $contentObjectAttribute )
    {
        $contentTmp = json_decode( $contentObjectAttribute->attribute( 'data_text' ) );
        $content = array( 'json' => array(), 
                          'perioddetails' => array() );
        if( isset( $contentTmp->include ) && count( $contentTmp->include ) > 0 )
        {
            $include = array();
            $firststartdate = $lastenddate = 0;
            foreach( $contentTmp->include as $key => $contentIncludeItem )
            {
                // initialize include
                $startdate = new DateTime( $contentIncludeItem->start );
                $starttimestamp = $startdate->getTimestamp();
                $include[$key]['starttime'] = $starttimestamp;
                $include[$key]['start'] = $contentIncludeItem->start;
                $enddate = new DateTime( $contentIncludeItem->end );
                $endtimestamp = $enddate->getTimestamp();
                $include[$key]['endtime'] = $endtimestamp;
                $include[$key]['end'] = $contentIncludeItem->end;
                if( isset( $contentIncludeItem->weekdays ) )
                {
                    $weekdays = array();
                    // ksort doesn't work here (don't know why) so we use a foreach for reindexing
                    foreach($contentIncludeItem->weekdays as $weekday)
                    {
                        $weekdays[] = $weekday;
                    }
                    $include[$key]['weekdays'] = $weekdays;
                }
                // get the first start date and the last end date of all periods
                if( $starttimestamp < $firststartdate || $firststartdate == 0 )
                {
                    $firststartdate = $starttimestamp;
                    if( isset( $include[$key]['weekdays'] ) && count( $include[$key]['weekdays'] ) > 0 && count( $include[$key]['weekdays'] ) < 7 )
                    {
                        $starttimeTmp = clone $startdate;
                        eZPublishEvent::checkWeekday( $starttimeTmp, $include[$key]['weekdays'], 'firststartdate' );
                        $firststartdate = $starttimeTmp->getTimestamp();
                        unset($starttimeTmp);
                    }
                }
                if( $endtimestamp > $lastenddate || $lastenddate == 0 )
                {
                    // if an one day event
                    if( $enddate->format( 'H:i' ) == '00:00' )
                    {
                        $checkEndDate = clone $enddate;
                        $checkEndDate->modify( '-1 day' );
                        if( $startdate->format( 'd.m.Y' ) == $checkEndDate->format( 'd.m.Y' ) )
                        {
                            $lastenddate = $starttimestamp;
                        }
                        else
                        {
                            $lastenddate = $endtimestamp;
                        }
                    }
                    else
                    {
                        $lastenddate = $endtimestamp;
                    }
                    if( isset( $include[$key]['weekdays'] ) && count( $include[$key]['weekdays'] ) > 0 && count( $include[$key]['weekdays'] ) < 7 )
                    {
                        $endtimeTmp = clone $enddate;
                        eZPublishEvent::checkWeekday( $endtimeTmp, $include[$key]['weekdays'], 'lastenddate' );
                        $lastenddate = $endtimeTmp->getTimestamp();
                        unset($endtimeTmp);
                    }
                }
            }
        }
        if( isset( $contentTmp->exclude ) && count( $contentTmp->exclude ) > 0 )
        {
            $exclude = array();
            foreach( $contentTmp->exclude as $key => $contentExcludeItem )
            {
                // initialize exclude
                $startdateExc = new DateTime( $contentExcludeItem->start );
                $starttimestamp = $startdateExc->getTimestamp();
                $exclude[$key]['starttime'] = $starttimestamp;
                $exclude[$key]['start'] = $contentExcludeItem->start;
                $enddateExc = new DateTime( $contentExcludeItem->end );
                $endtimestamp = $enddateExc->getTimestamp();
                $exclude[$key]['endtime'] = $endtimestamp;
                $exclude[$key]['end'] = $contentExcludeItem->end;
            }
        }
        if( isset( $include ) && count( $include ) > 0 )
        {
            $content['json']['include'] = $include;
        }
        if( isset( $exclude ) && count( $exclude ) > 0 )
        {
            $content['json']['exclude'] = $exclude;
        }
        if( $firststartdate > 0 )
        {
            $content['perioddetails']['firststartdate'] = $firststartdate;
        }
        if( $lastenddate > 0 )
        {
            $content['perioddetails']['lastenddate'] = $lastenddate;
        }
        #die(var_dump($content));
        return $content;
    }

    /*
     * Returns the meta data used for storing search indeces.
     */
    function metaData( $contentObjectAttribute )
    {
        $content = $this->objectAttributeContent( $this );
        return $content;
    }

    function hasObjectAttributeContent( $contentObjectAttribute )
    {
        return $contentObjectAttribute->attribute( "data_text" ) != '';
    }

    /*
     * Return string representation of an contentobjectattribute data for simplified export
     */
    function toString( $contentObjectAttribute )
    {
        return $contentObjectAttribute->attribute( 'data_text' );
    }

    /*!
     Sets the default value.
    */
    function initializeObjectAttribute( $contentObjectAttribute, $currentVersion, $originalContentObjectAttribute )
    {
        $contentObjectAttribute->setAttribute( "data_text", $originalContentObjectAttribute->attribute( "data_text" ) );
    }

    function validateDateTime( $now, $version, $checktime1, $checktime2 = false, $ezpublisheventIni = false )
    {
        if( $checktime1 instanceof DateTime && ( $checktime2 === false || ( $checktime2 !== false && $checktime2 instanceof DateTime ) ) )
        {
            if( $checktime2 !== false )
            {
                $maxPeriodForEvent = '+1 year';
                if( $ezpublisheventIni->hasVariable( 'Settings', 'MaxPeriodForEvent' ) )
                    $maxPeriodForEvent = $ezpublisheventIni->variable( 'Settings', 'MaxPeriodForEvent' );
                if( $checktime2->getTimestamp() < $now && $version !== false && $version->Version == 1 )
                {
                    return array( 'error' => ezpI18n::tr( 'extension/ezpublish-event', 'Select an end date in the future.' ) );
                }
                if( $checktime1->getTimestamp() > $checktime2->getTimestamp() )
                {
                    return array( 'error' => ezpI18n::tr( 'extension/ezpublish-event', 'Select an end date newer then the start date.' ) );
                }
                $tmpChecktime1 = clone $checktime1;
                $tmpChecktime1->modify( $maxPeriodForEvent );
                if( $tmpChecktime1->getTimestamp() < $checktime2->getTimestamp() )
                {
                    return array( 'error' => ezpI18n::tr( 'extension/ezpublish-event', 'Maximum period of an event is exceeded.' ) );
                }
            }
        }
        else
        {
            if( !$checktime1 instanceof DateTime )
            {
                return array( 'error' => ezpI18n::tr( 'extension/ezpublish-event', 'Start date is not instanceof DateTime.' ) );
            }
            if( $checktime2 !== false && !$checktime2 instanceof DateTime )
            {
                return array( 'error' => ezpI18n::tr( 'extension/ezpublish-event', 'End date is not instanceof DateTime.' ) );
            }
        }
        return array( 'state' => true );
    }
}

eZDataType::register( eZPublishEventType::DATA_TYPE_STRING, "eZPublishEventType" );