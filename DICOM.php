<?php
/* vim: set expandtab tabstop=4 shiftwidth=4: */
// +----------------------------------------------------------------------+
// | PHP version 4                                                        |
// +----------------------------------------------------------------------+
// | Copyright (c) 1997-2002 The PHP Group                                |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.0 of the PHP license,       |
// | that is bundled with this package in the file LICENSE, and is        |
// | available through the world-wide-web at                              |
// | http://www.php.net/license/2_02.txt.                                 |
// | If you did not receive a copy of the PHP license and are unable to   |
// | obtain it through the world-wide-web, please send a note to          |
// | license@php.net so we can mail you a copy immediately.               |
// +----------------------------------------------------------------------+
// | Author: Xavier Noguer <xnoguer@php.net>                              |
// +----------------------------------------------------------------------+
//
// $Id$

require_once('PEAR.php');
require_once('File/DICOM/Element.php');


define('FILE_DICOM_VR_TYPE_EXPLICIT_32_BITS', 0);
define('FILE_DICOM_VR_TYPE_EXPLICIT_16_BITS', 1);
define('FILE_DICOM_VR_TYPE_IMPLICIT', 2);

/**
* This class allows reading and modifying of DICOM files
*
* @author   Xavier Noguer <xnoguer@php.net>
* @package  File_DICOM
*/
class File_DICOM extends PEAR
{
    /**
    * DICOM dictionary.
    * @var array
    */
    var $dict;

    /**
    * Flag indicating if the current file is a DICM file or a NEMA file.
    * true => DICM, false => NEMA.
    *
    * @var bool
    */
    var $is_dicm;

    /**
    * Currently open file.
    * @var string
    */
    var $current_file;

    /**
    * Initial 0x80 bytes of last read file
    * @var string
    */
    var $_preamble_buff;

    /**
    * Array of DICOM elements indexed by group and element index
    * @var array
    */
    var $_elements;

    /**
    * Array of DICOM elements indexed by name
    * @var array
    */
    var $_elements_by_name;

    /**
    * Constructor.
    * It creates a File_DICOM object.
    *
    * @access public
    */
    function File_DICOM()
    {
        /**
        * Initialize dictionary.
        * Definitions of fields of DICOM headers.
        * Andrew Crabb (ahc@jhu.edu), May 2002.
        *
        * ------------------------------------------------------------------------
        * NOT FOR MEDICAL USE.  This file is provided purely for experimental use.
        * ------------------------------------------------------------------------
        */
$this->dict[0x0000][0x0000] = array('UL','1','GroupLength');
$this->dict[0x0000][0x0001] = array('UL','1','CommandLengthToEnd');
$this->dict[0x0000][0x0002] = array('UI','1','AffectedSOPClassUID');
$this->dict[0x0000][0x0003] = array('UI','1','RequestedSOPClassUID');
$this->dict[0x0000][0x0010] = array('CS','1','CommandRecognitionCode');
$this->dict[0x0000][0x0100] = array('US','1','CommandField');
$this->dict[0x0000][0x0110] = array('US','1','MessageID');
$this->dict[0x0000][0x0120] = array('US','1','MessageIDBeingRespondedTo');
$this->dict[0x0000][0x0200] = array('AE','1','Initiator');
$this->dict[0x0000][0x0300] = array('AE','1','Receiver');
$this->dict[0x0000][0x0400] = array('AE','1','FindLocation');
$this->dict[0x0000][0x0600] = array('AE','1','MoveDestination');
$this->dict[0x0000][0x0700] = array('US','1','Priority');
$this->dict[0x0000][0x0800] = array('US','1','DataSetType');
$this->dict[0x0000][0x0850] = array('US','1','NumberOfMatches');
$this->dict[0x0000][0x0860] = array('US','1','ResponseSequenceNumber');
$this->dict[0x0000][0x0900] = array('US','1','Status');
$this->dict[0x0000][0x0901] = array('AT','1-n','OffendingElement');
$this->dict[0x0000][0x0902] = array('LO','1','ErrorComment');
$this->dict[0x0000][0x0903] = array('US','1','ErrorID');
$this->dict[0x0000][0x0904] = array('OT','1-n','ErrorInformation');
$this->dict[0x0000][0x1000] = array('UI','1','AffectedSOPInstanceUID');
$this->dict[0x0000][0x1001] = array('UI','1','RequestedSOPInstanceUID');
$this->dict[0x0000][0x1002] = array('US','1','EventTypeID');
$this->dict[0x0000][0x1003] = array('OT','1-n','EventInformation');
$this->dict[0x0000][0x1005] = array('AT','1-n','AttributeIdentifierList');
$this->dict[0x0000][0x1007] = array('AT','1-n','ModificationList');
$this->dict[0x0000][0x1008] = array('US','1','ActionTypeID');
$this->dict[0x0000][0x1009] = array('OT','1-n','ActionInformation');
$this->dict[0x0000][0x1013] = array('UI','1-n','SuccessfulSOPInstanceUIDList');
$this->dict[0x0000][0x1014] = array('UI','1-n','FailedSOPInstanceUIDList');
$this->dict[0x0000][0x1015] = array('UI','1-n','WarningSOPInstanceUIDList');
$this->dict[0x0000][0x1020] = array('US','1','NumberOfRemainingSuboperations');
$this->dict[0x0000][0x1021] = array('US','1','NumberOfCompletedSuboperations');
$this->dict[0x0000][0x1022] = array('US','1','NumberOfFailedSuboperations');
$this->dict[0x0000][0x1023] = array('US','1','NumberOfWarningSuboperations');
$this->dict[0x0000][0x1030] = array('AE','1','MoveOriginatorApplicationEntityTitle');
$this->dict[0x0000][0x1031] = array('US','1','MoveOriginatorMessageID');
$this->dict[0x0000][0x4000] = array('AT','1','DialogReceiver');
$this->dict[0x0000][0x4010] = array('AT','1','TerminalType');
$this->dict[0x0000][0x5010] = array('SH','1','MessageSetID');
$this->dict[0x0000][0x5020] = array('SH','1','EndMessageSet');
$this->dict[0x0000][0x5110] = array('AT','1','DisplayFormat');
$this->dict[0x0000][0x5120] = array('AT','1','PagePositionID');
$this->dict[0x0000][0x5130] = array('CS','1','TextFormatID');
$this->dict[0x0000][0x5140] = array('CS','1','NormalReverse');
$this->dict[0x0000][0x5150] = array('CS','1','AddGrayScale');
$this->dict[0x0000][0x5160] = array('CS','1','Borders');
$this->dict[0x0000][0x5170] = array('IS','1','Copies');
$this->dict[0x0000][0x5180] = array('CS','1','OldMagnificationType');
$this->dict[0x0000][0x5190] = array('CS','1','Erase');
$this->dict[0x0000][0x51A0] = array('CS','1','Print');
$this->dict[0x0000][0x51B0] = array('US','1-n','Overlays');
$this->dict[0x0002][0x0000] = array('UL','1','MetaElementGroupLength');
$this->dict[0x0002][0x0001] = array('OB','1','FileMetaInformationVersion');
$this->dict[0x0002][0x0002] = array('UI','1','MediaStorageSOPClassUID');
$this->dict[0x0002][0x0003] = array('UI','1','MediaStorageSOPInstanceUID');
$this->dict[0x0002][0x0010] = array('UI','1','TransferSyntaxUID');
$this->dict[0x0002][0x0012] = array('UI','1','ImplementationClassUID');
$this->dict[0x0002][0x0013] = array('SH','1','ImplementationVersionName');
$this->dict[0x0002][0x0016] = array('AE','1','SourceApplicationEntityTitle');
$this->dict[0x0002][0x0100] = array('UI','1','PrivateInformationCreatorUID');
$this->dict[0x0002][0x0102] = array('OB','1','PrivateInformation');
$this->dict[0x0004][0x0000] = array('UL','1','FileSetGroupLength');
$this->dict[0x0004][0x1130] = array('CS','1','FileSetID');
$this->dict[0x0004][0x1141] = array('CS','8','FileSetDescriptorFileID');
$this->dict[0x0004][0x1142] = array('CS','1','FileSetCharacterSet');
$this->dict[0x0004][0x1200] = array('UL','1','RootDirectoryFirstRecord');
$this->dict[0x0004][0x1202] = array('UL','1','RootDirectoryLastRecord');
$this->dict[0x0004][0x1212] = array('US','1','FileSetConsistencyFlag');
$this->dict[0x0004][0x1220] = array('SQ','1','DirectoryRecordSequence');
$this->dict[0x0004][0x1400] = array('UL','1','NextDirectoryRecordOffset');
$this->dict[0x0004][0x1410] = array('US','1','RecordInUseFlag');
$this->dict[0x0004][0x1420] = array('UL','1','LowerLevelDirectoryOffset');
$this->dict[0x0004][0x1430] = array('CS','1','DirectoryRecordType');
$this->dict[0x0004][0x1432] = array('UI','1','PrivateRecordUID');
$this->dict[0x0004][0x1500] = array('CS','8','ReferencedFileID');
$this->dict[0x0004][0x1504] = array('UL','1','DirectoryRecordOffset');
$this->dict[0x0004][0x1510] = array('UI','1','ReferencedSOPClassUIDInFile');
$this->dict[0x0004][0x1511] = array('UI','1','ReferencedSOPInstanceUIDInFile');
$this->dict[0x0004][0x1512] = array('UI','1','ReferencedTransferSyntaxUIDInFile');
$this->dict[0x0004][0x1600] = array('UL','1','NumberOfReferences');
$this->dict[0x0008][0x0000] = array('UL','1','IdentifyingGroupLength');
$this->dict[0x0008][0x0001] = array('UL','1','LengthToEnd');
$this->dict[0x0008][0x0005] = array('CS','1','SpecificCharacterSet');
$this->dict[0x0008][0x0008] = array('CS','1-n','ImageType');
$this->dict[0x0008][0x000A] = array('US','1','SequenceItemNumber');
$this->dict[0x0008][0x0010] = array('CS','1','RecognitionCode');
$this->dict[0x0008][0x0012] = array('DA','1','InstanceCreationDate');
$this->dict[0x0008][0x0013] = array('TM','1','InstanceCreationTime');
$this->dict[0x0008][0x0014] = array('UI','1','InstanceCreatorUID');
$this->dict[0x0008][0x0016] = array('UI','1','SOPClassUID');
$this->dict[0x0008][0x0018] = array('UI','1','SOPInstanceUID');
$this->dict[0x0008][0x0020] = array('DA','1','StudyDate');
$this->dict[0x0008][0x0021] = array('DA','1','SeriesDate');
$this->dict[0x0008][0x0022] = array('DA','1','AcquisitionDate');
$this->dict[0x0008][0x0023] = array('DA','1','ImageDate');
$this->dict[0x0008][0x0024] = array('DA','1','OverlayDate');
$this->dict[0x0008][0x0025] = array('DA','1','CurveDate');
$this->dict[0x0008][0x002A] = array('DT','1','AcquisitionDatetime');
$this->dict[0x0008][0x0030] = array('TM','1','StudyTime');
$this->dict[0x0008][0x0031] = array('TM','1','SeriesTime');
$this->dict[0x0008][0x0032] = array('TM','1','AcquisitionTime');
$this->dict[0x0008][0x0033] = array('TM','1','ImageTime');
$this->dict[0x0008][0x0034] = array('TM','1','OverlayTime');
$this->dict[0x0008][0x0035] = array('TM','1','CurveTime');
$this->dict[0x0008][0x0040] = array('US','1','OldDataSetType');
$this->dict[0x0008][0x0041] = array('LT','1','OldDataSetSubtype');
$this->dict[0x0008][0x0042] = array('CS','1','NuclearMedicineSeriesType');
$this->dict[0x0008][0x0050] = array('SH','1','AccessionNumber');
$this->dict[0x0008][0x0052] = array('CS','1','QueryRetrieveLevel');
$this->dict[0x0008][0x0054] = array('AE','1-n','RetrieveAETitle');
$this->dict[0x0008][0x0058] = array('UI','1-n','DataSetFailedSOPInstanceUIDList');
$this->dict[0x0008][0x0060] = array('CS','1','Modality');
$this->dict[0x0008][0x0061] = array('CS','1-n','ModalitiesInStudy');
$this->dict[0x0008][0x0064] = array('CS','1','ConversionType');
$this->dict[0x0008][0x0068] = array('CS','1','PresentationIntentType');
$this->dict[0x0008][0x0070] = array('LO','1','Manufacturer');
$this->dict[0x0008][0x0080] = array('LO','1','InstitutionName');
$this->dict[0x0008][0x0081] = array('ST','1','InstitutionAddress');
$this->dict[0x0008][0x0082] = array('SQ','1','InstitutionCodeSequence');
$this->dict[0x0008][0x0090] = array('PN','1','ReferringPhysicianName');
$this->dict[0x0008][0x0092] = array('ST','1','ReferringPhysicianAddress');
$this->dict[0x0008][0x0094] = array('SH','1-n','ReferringPhysicianTelephoneNumber');
$this->dict[0x0008][0x0100] = array('SH','1','CodeValue');
$this->dict[0x0008][0x0102] = array('SH','1','CodingSchemeDesignator');
$this->dict[0x0008][0x0103] = array('SH','1','CodingSchemeVersion');
$this->dict[0x0008][0x0104] = array('LO','1','CodeMeaning');
$this->dict[0x0008][0x0105] = array('CS','1','MappingResource');
$this->dict[0x0008][0x0106] = array('DT','1','ContextGroupVersion');
$this->dict[0x0008][0x0107] = array('DT','1','ContextGroupLocalVersion');
$this->dict[0x0008][0x010B] = array('CS','1','CodeSetExtensionFlag');
$this->dict[0x0008][0x010C] = array('UI','1','PrivateCodingSchemeCreatorUID');
$this->dict[0x0008][0x010D] = array('UI','1','CodeSetExtensionCreatorUID');
$this->dict[0x0008][0x010F] = array('CS','1','ContextIdentifier');
$this->dict[0x0008][0x0201] = array('SH','1','TimezoneOffsetFromUTC');
$this->dict[0x0008][0x1000] = array('AE','1','NetworkID');
$this->dict[0x0008][0x1010] = array('SH','1','StationName');
$this->dict[0x0008][0x1030] = array('LO','1','StudyDescription');
$this->dict[0x0008][0x1032] = array('SQ','1','ProcedureCodeSequence');
$this->dict[0x0008][0x103E] = array('LO','1','SeriesDescription');
$this->dict[0x0008][0x1040] = array('LO','1','InstitutionalDepartmentName');
$this->dict[0x0008][0x1048] = array('PN','1-n','PhysicianOfRecord');
$this->dict[0x0008][0x1050] = array('PN','1-n','PerformingPhysicianName');
$this->dict[0x0008][0x1060] = array('PN','1-n','PhysicianReadingStudy');
$this->dict[0x0008][0x1070] = array('PN','1-n','OperatorName');
$this->dict[0x0008][0x1080] = array('LO','1-n','AdmittingDiagnosisDescription');
$this->dict[0x0008][0x1084] = array('SQ','1','AdmittingDiagnosisCodeSequence');
$this->dict[0x0008][0x1090] = array('LO','1','ManufacturerModelName');
$this->dict[0x0008][0x1100] = array('SQ','1','ReferencedResultsSequence');
$this->dict[0x0008][0x1110] = array('SQ','1','ReferencedStudySequence');
$this->dict[0x0008][0x1111] = array('SQ','1','ReferencedStudyComponentSequence');
$this->dict[0x0008][0x1115] = array('SQ','1','ReferencedSeriesSequence');
$this->dict[0x0008][0x1120] = array('SQ','1','ReferencedPatientSequence');
$this->dict[0x0008][0x1125] = array('SQ','1','ReferencedVisitSequence');
$this->dict[0x0008][0x1130] = array('SQ','1','ReferencedOverlaySequence');
$this->dict[0x0008][0x1140] = array('SQ','1','ReferencedImageSequence');
$this->dict[0x0008][0x1145] = array('SQ','1','ReferencedCurveSequence');
$this->dict[0x0008][0x114A] = array('SQ','1','ReferencedInstanceSequence');
$this->dict[0x0008][0x114B] = array('LO','1','ReferenceDescription');
$this->dict[0x0008][0x1150] = array('UI','1','ReferencedSOPClassUID');
$this->dict[0x0008][0x1155] = array('UI','1','ReferencedSOPInstanceUID');
$this->dict[0x0008][0x115A] = array('UI','1-n','SOPClassesSupported');
$this->dict[0x0008][0x1160] = array('IS','1','ReferencedFrameNumber');
$this->dict[0x0008][0x1195] = array('UI','1','TransactionUID');
$this->dict[0x0008][0x1197] = array('US','1','FailureReason');
$this->dict[0x0008][0x1198] = array('SQ','1','FailedSOPSequence');
$this->dict[0x0008][0x1199] = array('SQ','1','ReferencedSOPSequence');
$this->dict[0x0008][0x2110] = array('CS','1','LossyImageCompression');
$this->dict[0x0008][0x2111] = array('ST','1','DerivationDescription');
$this->dict[0x0008][0x2112] = array('SQ','1','SourceImageSequence');
$this->dict[0x0008][0x2120] = array('SH','1','StageName');
$this->dict[0x0008][0x2122] = array('IS','1','StageNumber');
$this->dict[0x0008][0x2124] = array('IS','1','NumberOfStages');
$this->dict[0x0008][0x2128] = array('IS','1','ViewNumber');
$this->dict[0x0008][0x2129] = array('IS','1','NumberOfEventTimers');
$this->dict[0x0008][0x212A] = array('IS','1','NumberOfViewsInStage');
$this->dict[0x0008][0x2130] = array('DS','1-n','EventElapsedTime');
$this->dict[0x0008][0x2132] = array('LO','1-n','EventTimerName');
$this->dict[0x0008][0x2142] = array('IS','1','StartTrim');
$this->dict[0x0008][0x2143] = array('IS','1','StopTrim');
$this->dict[0x0008][0x2144] = array('IS','1','RecommendedDisplayFrameRate');
$this->dict[0x0008][0x2200] = array('CS','1','TransducerPosition');
$this->dict[0x0008][0x2204] = array('CS','1','TransducerOrientation');
$this->dict[0x0008][0x2208] = array('CS','1','AnatomicStructure');
$this->dict[0x0008][0x2218] = array('SQ','1','AnatomicRegionSequence');
$this->dict[0x0008][0x2220] = array('SQ','1','AnatomicRegionModifierSequence');
$this->dict[0x0008][0x2228] = array('SQ','1','PrimaryAnatomicStructureSequence');
$this->dict[0x0008][0x2229] = array('SQ','1','AnatomicStructureSpaceOrRegionSequence');
$this->dict[0x0008][0x2230] = array('SQ','1','PrimaryAnatomicStructureModifierSequence');
$this->dict[0x0008][0x2240] = array('SQ','1','TransducerPositionSequence');
$this->dict[0x0008][0x2242] = array('SQ','1','TransducerPositionModifierSequence');
$this->dict[0x0008][0x2244] = array('SQ','1','TransducerOrientationSequence');
$this->dict[0x0008][0x2246] = array('SQ','1','TransducerOrientationModifierSequence');
$this->dict[0x0008][0x4000] = array('LT','1-n','IdentifyingComments');
$this->dict[0x0010][0x0000] = array('UL','1','PatientGroupLength');
$this->dict[0x0010][0x0010] = array('PN','1','PatientName');
$this->dict[0x0010][0x0020] = array('LO','1','PatientID');
$this->dict[0x0010][0x0021] = array('LO','1','IssuerOfPatientID');
$this->dict[0x0010][0x0030] = array('DA','1','PatientBirthDate');
$this->dict[0x0010][0x0032] = array('TM','1','PatientBirthTime');
$this->dict[0x0010][0x0040] = array('CS','1','PatientSex');
$this->dict[0x0010][0x0050] = array('SQ','1','PatientInsurancePlanCodeSequence');
$this->dict[0x0010][0x1000] = array('LO','1-n','OtherPatientID');
$this->dict[0x0010][0x1001] = array('PN','1-n','OtherPatientName');
$this->dict[0x0010][0x1005] = array('PN','1','PatientBirthName');
$this->dict[0x0010][0x1010] = array('AS','1','PatientAge');
$this->dict[0x0010][0x1020] = array('DS','1','PatientSize');
$this->dict[0x0010][0x1030] = array('DS','1','PatientWeight');
$this->dict[0x0010][0x1040] = array('LO','1','PatientAddress');
$this->dict[0x0010][0x1050] = array('LT','1-n','InsurancePlanIdentification');
$this->dict[0x0010][0x1060] = array('PN','1','PatientMotherBirthName');
$this->dict[0x0010][0x1080] = array('LO','1','MilitaryRank');
$this->dict[0x0010][0x1081] = array('LO','1','BranchOfService');
$this->dict[0x0010][0x1090] = array('LO','1','MedicalRecordLocator');
$this->dict[0x0010][0x2000] = array('LO','1-n','MedicalAlerts');
$this->dict[0x0010][0x2110] = array('LO','1-n','ContrastAllergies');
$this->dict[0x0010][0x2150] = array('LO','1','CountryOfResidence');
$this->dict[0x0010][0x2152] = array('LO','1','RegionOfResidence');
$this->dict[0x0010][0x2154] = array('SH','1-n','PatientTelephoneNumber');
$this->dict[0x0010][0x2160] = array('SH','1','EthnicGroup');
$this->dict[0x0010][0x2180] = array('SH','1','Occupation');
$this->dict[0x0010][0x21A0] = array('CS','1','SmokingStatus');
$this->dict[0x0010][0x21B0] = array('LT','1','AdditionalPatientHistory');
$this->dict[0x0010][0x21C0] = array('US','1','PregnancyStatus');
$this->dict[0x0010][0x21D0] = array('DA','1','LastMenstrualDate');
$this->dict[0x0010][0x21F0] = array('LO','1','PatientReligiousPreference');
$this->dict[0x0010][0x4000] = array('LT','1','PatientComments');
$this->dict[0x0018][0x0000] = array('UL','1','AcquisitionGroupLength');
$this->dict[0x0018][0x0010] = array('LO','1','ContrastBolusAgent');
$this->dict[0x0018][0x0012] = array('SQ','1','ContrastBolusAgentSequence');
$this->dict[0x0018][0x0014] = array('SQ','1','ContrastBolusAdministrationRouteSequence');
$this->dict[0x0018][0x0015] = array('CS','1','BodyPartExamined');
$this->dict[0x0018][0x0020] = array('CS','1-n','ScanningSequence');
$this->dict[0x0018][0x0021] = array('CS','1-n','SequenceVariant');
$this->dict[0x0018][0x0022] = array('CS','1-n','ScanOptions');
$this->dict[0x0018][0x0023] = array('CS','1','MRAcquisitionType');
$this->dict[0x0018][0x0024] = array('SH','1','SequenceName');
$this->dict[0x0018][0x0025] = array('CS','1','AngioFlag');
$this->dict[0x0018][0x0026] = array('SQ','1','InterventionDrugInformationSequence');
$this->dict[0x0018][0x0027] = array('TM','1','InterventionDrugStopTime');
$this->dict[0x0018][0x0028] = array('DS','1','InterventionDrugDose');
$this->dict[0x0018][0x0029] = array('SQ','1','InterventionalDrugSequence');
$this->dict[0x0018][0x002A] = array('SQ','1','AdditionalDrugSequence');
$this->dict[0x0018][0x0030] = array('LO','1-n','Radionuclide');
$this->dict[0x0018][0x0031] = array('LO','1-n','Radiopharmaceutical');
$this->dict[0x0018][0x0032] = array('DS','1','EnergyWindowCenterline');
$this->dict[0x0018][0x0033] = array('DS','1-n','EnergyWindowTotalWidth');
$this->dict[0x0018][0x0034] = array('LO','1','InterventionalDrugName');
$this->dict[0x0018][0x0035] = array('TM','1','InterventionalDrugStartTime');
$this->dict[0x0018][0x0036] = array('SQ','1','InterventionalTherapySequence');
$this->dict[0x0018][0x0037] = array('CS','1','TherapyType');
$this->dict[0x0018][0x0038] = array('CS','1','InterventionalStatus');
$this->dict[0x0018][0x0039] = array('CS','1','TherapyDescription');
$this->dict[0x0018][0x0040] = array('IS','1','CineRate');
$this->dict[0x0018][0x0050] = array('DS','1','SliceThickness');
$this->dict[0x0018][0x0060] = array('DS','1','KVP');
$this->dict[0x0018][0x0070] = array('IS','1','CountsAccumulated');
$this->dict[0x0018][0x0071] = array('CS','1','AcquisitionTerminationCondition');
$this->dict[0x0018][0x0072] = array('DS','1','EffectiveSeriesDuration');
$this->dict[0x0018][0x0073] = array('CS','1','AcquisitionStartCondition');
$this->dict[0x0018][0x0074] = array('IS','1','AcquisitionStartConditionData');
$this->dict[0x0018][0x0075] = array('IS','1','AcquisitionTerminationConditionData');
$this->dict[0x0018][0x0080] = array('DS','1','RepetitionTime');
$this->dict[0x0018][0x0081] = array('DS','1','EchoTime');
$this->dict[0x0018][0x0082] = array('DS','1','InversionTime');
$this->dict[0x0018][0x0083] = array('DS','1','NumberOfAverages');
$this->dict[0x0018][0x0084] = array('DS','1','ImagingFrequency');
$this->dict[0x0018][0x0085] = array('SH','1','ImagedNucleus');
$this->dict[0x0018][0x0086] = array('IS','1-n','EchoNumber');
$this->dict[0x0018][0x0087] = array('DS','1','MagneticFieldStrength');
$this->dict[0x0018][0x0088] = array('DS','1','SpacingBetweenSlices');
$this->dict[0x0018][0x0089] = array('IS','1','NumberOfPhaseEncodingSteps');
$this->dict[0x0018][0x0090] = array('DS','1','DataCollectionDiameter');
$this->dict[0x0018][0x0091] = array('IS','1','EchoTrainLength');
$this->dict[0x0018][0x0093] = array('DS','1','PercentSampling');
$this->dict[0x0018][0x0094] = array('DS','1','PercentPhaseFieldOfView');
$this->dict[0x0018][0x0095] = array('DS','1','PixelBandwidth');
$this->dict[0x0018][0x1000] = array('LO','1','DeviceSerialNumber');
$this->dict[0x0018][0x1004] = array('LO','1','PlateID');
$this->dict[0x0018][0x1010] = array('LO','1','SecondaryCaptureDeviceID');
$this->dict[0x0018][0x1011] = array('LO','1','HardcopyCreationDeviceID');
$this->dict[0x0018][0x1012] = array('DA','1','DateOfSecondaryCapture');
$this->dict[0x0018][0x1014] = array('TM','1','TimeOfSecondaryCapture');
$this->dict[0x0018][0x1016] = array('LO','1','SecondaryCaptureDeviceManufacturer');
$this->dict[0x0018][0x1017] = array('LO','1','HardcopyDeviceManufacturer');
$this->dict[0x0018][0x1018] = array('LO','1','SecondaryCaptureDeviceManufacturerModelName');
$this->dict[0x0018][0x1019] = array('LO','1-n','SecondaryCaptureDeviceSoftwareVersion');
$this->dict[0x0018][0x101A] = array('LO','1-n','HardcopyDeviceSoftwareVersion');
$this->dict[0x0018][0x101B] = array('LO','1','HardcopyDeviceManfuacturersModelName');
$this->dict[0x0018][0x1020] = array('LO','1-n','SoftwareVersion');
$this->dict[0x0018][0x1022] = array('SH','1','VideoImageFormatAcquired');
$this->dict[0x0018][0x1023] = array('LO','1','DigitalImageFormatAcquired');
$this->dict[0x0018][0x1030] = array('LO','1','ProtocolName');
$this->dict[0x0018][0x1040] = array('LO','1','ContrastBolusRoute');
$this->dict[0x0018][0x1041] = array('DS','1','ContrastBolusVolume');
$this->dict[0x0018][0x1042] = array('TM','1','ContrastBolusStartTime');
$this->dict[0x0018][0x1043] = array('TM','1','ContrastBolusStopTime');
$this->dict[0x0018][0x1044] = array('DS','1','ContrastBolusTotalDose');
$this->dict[0x0018][0x1045] = array('IS','1-n','SyringeCounts');
$this->dict[0x0018][0x1046] = array('DS','1-n','ContrastFlowRate');
$this->dict[0x0018][0x1047] = array('DS','1-n','ContrastFlowDuration');
$this->dict[0x0018][0x1048] = array('CS','1','ContrastBolusIngredient');
$this->dict[0x0018][0x1049] = array('DS','1','ContrastBolusIngredientConcentration');
$this->dict[0x0018][0x1050] = array('DS','1','SpatialResolution');
$this->dict[0x0018][0x1060] = array('DS','1','TriggerTime');
$this->dict[0x0018][0x1061] = array('LO','1','TriggerSourceOrType');
$this->dict[0x0018][0x1062] = array('IS','1','NominalInterval');
$this->dict[0x0018][0x1063] = array('DS','1','FrameTime');
$this->dict[0x0018][0x1064] = array('LO','1','FramingType');
$this->dict[0x0018][0x1065] = array('DS','1-n','FrameTimeVector');
$this->dict[0x0018][0x1066] = array('DS','1','FrameDelay');
$this->dict[0x0018][0x1067] = array('DS','1','ImageTriggerDelay');
$this->dict[0x0018][0x1068] = array('DS','1','MultiplexGroupTimeOffset');
$this->dict[0x0018][0x1069] = array('DS','1','TriggerTimeOffset');
$this->dict[0x0018][0x106A] = array('CS','1','SynchronizationTrigger');
$this->dict[0x0018][0x106C] = array('US','2','SynchronizationChannel');
$this->dict[0x0018][0x106E] = array('UL','1','TriggerSamplePosition');
$this->dict[0x0018][0x1070] = array('LO','1-n','RadionuclideRoute');
$this->dict[0x0018][0x1071] = array('DS','1-n','RadionuclideVolume');
$this->dict[0x0018][0x1072] = array('TM','1-n','RadionuclideStartTime');
$this->dict[0x0018][0x1073] = array('TM','1-n','RadionuclideStopTime');
$this->dict[0x0018][0x1074] = array('DS','1-n','RadionuclideTotalDose');
$this->dict[0x0018][0x1075] = array('DS','1','RadionuclideHalfLife');
$this->dict[0x0018][0x1076] = array('DS','1','RadionuclidePositronFraction');
$this->dict[0x0018][0x1077] = array('DS','1','RadiopharmaceuticalSpecificActivity');
$this->dict[0x0018][0x1080] = array('CS','1','BeatRejectionFlag');
$this->dict[0x0018][0x1081] = array('IS','1','LowRRValue');
$this->dict[0x0018][0x1082] = array('IS','1','HighRRValue');
$this->dict[0x0018][0x1083] = array('IS','1','IntervalsAcquired');
$this->dict[0x0018][0x1084] = array('IS','1','IntervalsRejected');
$this->dict[0x0018][0x1085] = array('LO','1','PVCRejection');
$this->dict[0x0018][0x1086] = array('IS','1','SkipBeats');
$this->dict[0x0018][0x1088] = array('IS','1','HeartRate');
$this->dict[0x0018][0x1090] = array('IS','1','CardiacNumberOfImages');
$this->dict[0x0018][0x1094] = array('IS','1','TriggerWindow');
$this->dict[0x0018][0x1100] = array('DS','1','ReconstructionDiameter');
$this->dict[0x0018][0x1110] = array('DS','1','DistanceSourceToDetector');
$this->dict[0x0018][0x1111] = array('DS','1','DistanceSourceToPatient');
$this->dict[0x0018][0x1114] = array('DS','1','EstimatedRadiographicMagnificationFactor');
$this->dict[0x0018][0x1120] = array('DS','1','GantryDetectorTilt');
$this->dict[0x0018][0x1121] = array('DS','1','GantryDetectorSlew');
$this->dict[0x0018][0x1130] = array('DS','1','TableHeight');
$this->dict[0x0018][0x1131] = array('DS','1','TableTraverse');
$this->dict[0x0018][0x1134] = array('DS','1','TableMotion');
$this->dict[0x0018][0x1135] = array('DS','1-n','TableVerticalIncrement');
$this->dict[0x0018][0x1136] = array('DS','1-n','TableLateralIncrement');
$this->dict[0x0018][0x1137] = array('DS','1-n','TableLongitudinalIncrement');
$this->dict[0x0018][0x1138] = array('DS','1','TableAngle');
$this->dict[0x0018][0x113A] = array('CS','1','TableType');
$this->dict[0x0018][0x1140] = array('CS','1','RotationDirection');
$this->dict[0x0018][0x1141] = array('DS','1','AngularPosition');
$this->dict[0x0018][0x1142] = array('DS','1-n','RadialPosition');
$this->dict[0x0018][0x1143] = array('DS','1','ScanArc');
$this->dict[0x0018][0x1144] = array('DS','1','AngularStep');
$this->dict[0x0018][0x1145] = array('DS','1','CenterOfRotationOffset');
$this->dict[0x0018][0x1146] = array('DS','1-n','RotationOffset');
$this->dict[0x0018][0x1147] = array('CS','1','FieldOfViewShape');
$this->dict[0x0018][0x1149] = array('IS','2','FieldOfViewDimension');
$this->dict[0x0018][0x1150] = array('IS','1','ExposureTime');
$this->dict[0x0018][0x1151] = array('IS','1','XrayTubeCurrent');
$this->dict[0x0018][0x1152] = array('IS','1','Exposure');
$this->dict[0x0018][0x1153] = array('IS','1','ExposureinuAs');
$this->dict[0x0018][0x1154] = array('DS','1','AveragePulseWidth');
$this->dict[0x0018][0x1155] = array('CS','1','RadiationSetting');
$this->dict[0x0018][0x1156] = array('CS','1','RectificationType');
$this->dict[0x0018][0x115A] = array('CS','1','RadiationMode');
$this->dict[0x0018][0x115E] = array('DS','1','ImageAreaDoseProduct');
$this->dict[0x0018][0x1160] = array('SH','1','FilterType');
$this->dict[0x0018][0x1161] = array('LO','1-n','TypeOfFilters');
$this->dict[0x0018][0x1162] = array('DS','1','IntensifierSize');
$this->dict[0x0018][0x1164] = array('DS','2','ImagerPixelSpacing');
$this->dict[0x0018][0x1166] = array('CS','1','Grid');
$this->dict[0x0018][0x1170] = array('IS','1','GeneratorPower');
$this->dict[0x0018][0x1180] = array('SH','1','CollimatorGridName');
$this->dict[0x0018][0x1181] = array('CS','1','CollimatorType');
$this->dict[0x0018][0x1182] = array('IS','1','FocalDistance');
$this->dict[0x0018][0x1183] = array('DS','1','XFocusCenter');
$this->dict[0x0018][0x1184] = array('DS','1','YFocusCenter');
$this->dict[0x0018][0x1190] = array('DS','1-n','FocalSpot');
$this->dict[0x0018][0x1191] = array('CS','1','AnodeTargetMaterial');
$this->dict[0x0018][0x11A0] = array('DS','1','BodyPartThickness');
$this->dict[0x0018][0x11A2] = array('DS','1','CompressionForce');
$this->dict[0x0018][0x1200] = array('DA','1-n','DateOfLastCalibration');
$this->dict[0x0018][0x1201] = array('TM','1-n','TimeOfLastCalibration');
$this->dict[0x0018][0x1210] = array('SH','1-n','ConvolutionKernel');
$this->dict[0x0018][0x1240] = array('IS','1-n','UpperLowerPixelValues');
$this->dict[0x0018][0x1242] = array('IS','1','ActualFrameDuration');
$this->dict[0x0018][0x1243] = array('IS','1','CountRate');
$this->dict[0x0018][0x1244] = array('US','1','PreferredPlaybackSequencing');
$this->dict[0x0018][0x1250] = array('SH','1','ReceivingCoil');
$this->dict[0x0018][0x1251] = array('SH','1','TransmittingCoil');
$this->dict[0x0018][0x1260] = array('SH','1','PlateType');
$this->dict[0x0018][0x1261] = array('LO','1','PhosphorType');
$this->dict[0x0018][0x1300] = array('IS','1','ScanVelocity');
$this->dict[0x0018][0x1301] = array('CS','1-n','WholeBodyTechnique');
$this->dict[0x0018][0x1302] = array('IS','1','ScanLength');
$this->dict[0x0018][0x1310] = array('US','4','AcquisitionMatrix');
$this->dict[0x0018][0x1312] = array('CS','1','PhaseEncodingDirection');
$this->dict[0x0018][0x1314] = array('DS','1','FlipAngle');
$this->dict[0x0018][0x1315] = array('CS','1','VariableFlipAngleFlag');
$this->dict[0x0018][0x1316] = array('DS','1','SAR');
$this->dict[0x0018][0x1318] = array('DS','1','dBdt');
$this->dict[0x0018][0x1400] = array('LO','1','AcquisitionDeviceProcessingDescription');
$this->dict[0x0018][0x1401] = array('LO','1','AcquisitionDeviceProcessingCode');
$this->dict[0x0018][0x1402] = array('CS','1','CassetteOrientation');
$this->dict[0x0018][0x1403] = array('CS','1','CassetteSize');
$this->dict[0x0018][0x1404] = array('US','1','ExposuresOnPlate');
$this->dict[0x0018][0x1405] = array('IS','1','RelativeXrayExposure');
$this->dict[0x0018][0x1450] = array('DS','1','ColumnAngulation');
$this->dict[0x0018][0x1460] = array('DS','1','TomoLayerHeight');
$this->dict[0x0018][0x1470] = array('DS','1','TomoAngle');
$this->dict[0x0018][0x1480] = array('DS','1','TomoTime');
$this->dict[0x0018][0x1490] = array('CS','1','TomoType');
$this->dict[0x0018][0x1491] = array('CS','1','TomoClass');
$this->dict[0x0018][0x1495] = array('IS','1','NumberofTomosynthesisSourceImages');
$this->dict[0x0018][0x1500] = array('CS','1','PositionerMotion');
$this->dict[0x0018][0x1508] = array('CS','1','PositionerType');
$this->dict[0x0018][0x1510] = array('DS','1','PositionerPrimaryAngle');
$this->dict[0x0018][0x1511] = array('DS','1','PositionerSecondaryAngle');
$this->dict[0x0018][0x1520] = array('DS','1-n','PositionerPrimaryAngleIncrement');
$this->dict[0x0018][0x1521] = array('DS','1-n','PositionerSecondaryAngleIncrement');
$this->dict[0x0018][0x1530] = array('DS','1','DetectorPrimaryAngle');
$this->dict[0x0018][0x1531] = array('DS','1','DetectorSecondaryAngle');
$this->dict[0x0018][0x1600] = array('CS','3','ShutterShape');
$this->dict[0x0018][0x1602] = array('IS','1','ShutterLeftVerticalEdge');
$this->dict[0x0018][0x1604] = array('IS','1','ShutterRightVerticalEdge');
$this->dict[0x0018][0x1606] = array('IS','1','ShutterUpperHorizontalEdge');
$this->dict[0x0018][0x1608] = array('IS','1','ShutterLowerHorizontalEdge');
$this->dict[0x0018][0x1610] = array('IS','1','CenterOfCircularShutter');
$this->dict[0x0018][0x1612] = array('IS','1','RadiusOfCircularShutter');
$this->dict[0x0018][0x1620] = array('IS','1-n','VerticesOfPolygonalShutter');
$this->dict[0x0018][0x1622] = array('US','1','ShutterPresentationValue');
$this->dict[0x0018][0x1623] = array('US','1','ShutterOverlayGroup');
$this->dict[0x0018][0x1700] = array('CS','3','CollimatorShape');
$this->dict[0x0018][0x1702] = array('IS','1','CollimatorLeftVerticalEdge');
$this->dict[0x0018][0x1704] = array('IS','1','CollimatorRightVerticalEdge');
$this->dict[0x0018][0x1706] = array('IS','1','CollimatorUpperHorizontalEdge');
$this->dict[0x0018][0x1708] = array('IS','1','CollimatorLowerHorizontalEdge');
$this->dict[0x0018][0x1710] = array('IS','1','CenterOfCircularCollimator');
$this->dict[0x0018][0x1712] = array('IS','1','RadiusOfCircularCollimator');
$this->dict[0x0018][0x1720] = array('IS','1-n','VerticesOfPolygonalCollimator');
$this->dict[0x0018][0x1800] = array('CS','1','AcquisitionTimeSynchronized');
$this->dict[0x0018][0x1801] = array('SH','1','TimeSource');
$this->dict[0x0018][0x1802] = array('CS','1','TimeDistributionProtocol');
$this->dict[0x0018][0x1810] = array('DT','1','AcquisitionTimestamp');
$this->dict[0x0018][0x4000] = array('LT','1-n','AcquisitionComments');
$this->dict[0x0018][0x5000] = array('SH','1-n','OutputPower');
$this->dict[0x0018][0x5010] = array('LO','3','TransducerData');
$this->dict[0x0018][0x5012] = array('DS','1','FocusDepth');
$this->dict[0x0018][0x5020] = array('LO','1','PreprocessingFunction');
$this->dict[0x0018][0x5021] = array('LO','1','PostprocessingFunction');
$this->dict[0x0018][0x5022] = array('DS','1','MechanicalIndex');
$this->dict[0x0018][0x5024] = array('DS','1','ThermalIndex');
$this->dict[0x0018][0x5026] = array('DS','1','CranialThermalIndex');
$this->dict[0x0018][0x5027] = array('DS','1','SoftTissueThermalIndex');
$this->dict[0x0018][0x5028] = array('DS','1','SoftTissueFocusThermalIndex');
$this->dict[0x0018][0x5029] = array('DS','1','SoftTissueSurfaceThermalIndex');
$this->dict[0x0018][0x5030] = array('DS','1','DynamicRange');
$this->dict[0x0018][0x5040] = array('DS','1','TotalGain');
$this->dict[0x0018][0x5050] = array('IS','1','DepthOfScanField');
$this->dict[0x0018][0x5100] = array('CS','1','PatientPosition');
$this->dict[0x0018][0x5101] = array('CS','1','ViewPosition');
$this->dict[0x0018][0x5104] = array('SQ','1','ProjectionEponymousNameCodeSequence');
$this->dict[0x0018][0x5210] = array('DS','6','ImageTransformationMatrix');
$this->dict[0x0018][0x5212] = array('DS','3','ImageTranslationVector');
$this->dict[0x0018][0x6000] = array('DS','1','Sensitivity');
$this->dict[0x0018][0x6011] = array('SQ','1','SequenceOfUltrasoundRegions');
$this->dict[0x0018][0x6012] = array('US','1','RegionSpatialFormat');
$this->dict[0x0018][0x6014] = array('US','1','RegionDataType');
$this->dict[0x0018][0x6016] = array('UL','1','RegionFlags');
$this->dict[0x0018][0x6018] = array('UL','1','RegionLocationMinX0');
$this->dict[0x0018][0x601A] = array('UL','1','RegionLocationMinY0');
$this->dict[0x0018][0x601C] = array('UL','1','RegionLocationMaxX1');
$this->dict[0x0018][0x601E] = array('UL','1','RegionLocationMaxY1');
$this->dict[0x0018][0x6020] = array('SL','1','ReferencePixelX0');
$this->dict[0x0018][0x6022] = array('SL','1','ReferencePixelY0');
$this->dict[0x0018][0x6024] = array('US','1','PhysicalUnitsXDirection');
$this->dict[0x0018][0x6026] = array('US','1','PhysicalUnitsYDirection');
$this->dict[0x0018][0x6028] = array('FD','1','ReferencePixelPhysicalValueX');
$this->dict[0x0018][0x602A] = array('FD','1','ReferencePixelPhysicalValueY');
$this->dict[0x0018][0x602C] = array('FD','1','PhysicalDeltaX');
$this->dict[0x0018][0x602E] = array('FD','1','PhysicalDeltaY');
$this->dict[0x0018][0x6030] = array('UL','1','TransducerFrequency');
$this->dict[0x0018][0x6031] = array('CS','1','TransducerType');
$this->dict[0x0018][0x6032] = array('UL','1','PulseRepetitionFrequency');
$this->dict[0x0018][0x6034] = array('FD','1','DopplerCorrectionAngle');
$this->dict[0x0018][0x6036] = array('FD','1','SteeringAngle');
$this->dict[0x0018][0x6038] = array('UL','1','DopplerSampleVolumeXPosition');
$this->dict[0x0018][0x603A] = array('UL','1','DopplerSampleVolumeYPosition');
$this->dict[0x0018][0x603C] = array('UL','1','TMLinePositionX0');
$this->dict[0x0018][0x603E] = array('UL','1','TMLinePositionY0');
$this->dict[0x0018][0x6040] = array('UL','1','TMLinePositionX1');
$this->dict[0x0018][0x6042] = array('UL','1','TMLinePositionY1');
$this->dict[0x0018][0x6044] = array('US','1','PixelComponentOrganization');
$this->dict[0x0018][0x6046] = array('UL','1','PixelComponentMask');
$this->dict[0x0018][0x6048] = array('UL','1','PixelComponentRangeStart');
$this->dict[0x0018][0x604A] = array('UL','1','PixelComponentRangeStop');
$this->dict[0x0018][0x604C] = array('US','1','PixelComponentPhysicalUnits');
$this->dict[0x0018][0x604E] = array('US','1','PixelComponentDataType');
$this->dict[0x0018][0x6050] = array('UL','1','NumberOfTableBreakPoints');
$this->dict[0x0018][0x6052] = array('UL','1-n','TableOfXBreakPoints');
$this->dict[0x0018][0x6054] = array('FD','1-n','TableOfYBreakPoints');
$this->dict[0x0018][0x6056] = array('UL','1','NumberOfTableEntries');
$this->dict[0x0018][0x6058] = array('UL','1-n','TableOfPixelValues');
$this->dict[0x0018][0x605A] = array('FL','1-n','TableOfParameterValues');
$this->dict[0x0018][0x7000] = array('CS','1','DetectorConditionsNominalFlag');
$this->dict[0x0018][0x7001] = array('DS','1','DetectorTemperature');
$this->dict[0x0018][0x7004] = array('CS','1','DetectorType');
$this->dict[0x0018][0x7005] = array('CS','1','DetectorConfiguration');
$this->dict[0x0018][0x7006] = array('LT','1','DetectorDescription');
$this->dict[0x0018][0x7008] = array('LT','1','DetectorMode');
$this->dict[0x0018][0x700A] = array('SH','1','DetectorID');
$this->dict[0x0018][0x700C] = array('DA','1','DateofLastDetectorCalibration');
$this->dict[0x0018][0x700E] = array('TM','1','TimeofLastDetectorCalibration');
$this->dict[0x0018][0x7010] = array('IS','1','ExposuresOnDetectorSinceLastCalibration');
$this->dict[0x0018][0x7011] = array('IS','1','ExposuresOnDetectorSinceManufactured');
$this->dict[0x0018][0x7012] = array('DS','1','DetectorTimeSinceLastExposure');
$this->dict[0x0018][0x7014] = array('DS','1','DetectorActiveTime');
$this->dict[0x0018][0x7016] = array('DS','1','DetectorActivationOffsetFromExposure');
$this->dict[0x0018][0x701A] = array('DS','2','DetectorBinning');
$this->dict[0x0018][0x7020] = array('DS','2','DetectorElementPhysicalSize');
$this->dict[0x0018][0x7022] = array('DS','2','DetectorElementSpacing');
$this->dict[0x0018][0x7024] = array('CS','1','DetectorActiveShape');
$this->dict[0x0018][0x7026] = array('DS','1-2','DetectorActiveDimensions');
$this->dict[0x0018][0x7028] = array('DS','2','DetectorActiveOrigin');
$this->dict[0x0018][0x7030] = array('DS','2','FieldofViewOrigin');
$this->dict[0x0018][0x7032] = array('DS','1','FieldofViewRotation');
$this->dict[0x0018][0x7034] = array('CS','1','FieldofViewHorizontalFlip');
$this->dict[0x0018][0x7040] = array('LT','1','GridAbsorbingMaterial');
$this->dict[0x0018][0x7041] = array('LT','1','GridSpacingMaterial');
$this->dict[0x0018][0x7042] = array('DS','1','GridThickness');
$this->dict[0x0018][0x7044] = array('DS','1','GridPitch');
$this->dict[0x0018][0x7046] = array('IS','2','GridAspectRatio');
$this->dict[0x0018][0x7048] = array('DS','1','GridPeriod');
$this->dict[0x0018][0x704C] = array('DS','1','GridFocalDistance');
$this->dict[0x0018][0x7050] = array('LT','1-n','FilterMaterial');
$this->dict[0x0018][0x7052] = array('DS','1-n','FilterThicknessMinimum');
$this->dict[0x0018][0x7054] = array('DS','1-n','FilterThicknessMaximum');
$this->dict[0x0018][0x7060] = array('CS','1','ExposureControlMode');
$this->dict[0x0018][0x7062] = array('LT','1','ExposureControlModeDescription');
$this->dict[0x0018][0x7064] = array('CS','1','ExposureStatus');
$this->dict[0x0018][0x7065] = array('DS','1','PhototimerSetting');
$this->dict[0x0020][0x0000] = array('UL','1','ImageGroupLength');
$this->dict[0x0020][0x000D] = array('UI','1','StudyInstanceUID');
$this->dict[0x0020][0x000E] = array('UI','1','SeriesInstanceUID');
$this->dict[0x0020][0x0010] = array('SH','1','StudyID');
$this->dict[0x0020][0x0011] = array('IS','1','SeriesNumber');
$this->dict[0x0020][0x0012] = array('IS','1','AcquisitionNumber');
$this->dict[0x0020][0x0013] = array('IS','1','ImageNumber');
$this->dict[0x0020][0x0014] = array('IS','1','IsotopeNumber');
$this->dict[0x0020][0x0015] = array('IS','1','PhaseNumber');
$this->dict[0x0020][0x0016] = array('IS','1','IntervalNumber');
$this->dict[0x0020][0x0017] = array('IS','1','TimeSlotNumber');
$this->dict[0x0020][0x0018] = array('IS','1','AngleNumber');
$this->dict[0x0020][0x0019] = array('IS','1','ItemNumber');
$this->dict[0x0020][0x0020] = array('CS','2','PatientOrientation');
$this->dict[0x0020][0x0022] = array('IS','1','OverlayNumber');
$this->dict[0x0020][0x0024] = array('IS','1','CurveNumber');
$this->dict[0x0020][0x0026] = array('IS','1','LUTNumber');
$this->dict[0x0020][0x0030] = array('DS','3','ImagePosition');
$this->dict[0x0020][0x0032] = array('DS','3','ImagePositionPatient');
$this->dict[0x0020][0x0035] = array('DS','6','ImageOrientation');
$this->dict[0x0020][0x0037] = array('DS','6','ImageOrientationPatient');
$this->dict[0x0020][0x0050] = array('DS','1','Location');
$this->dict[0x0020][0x0052] = array('UI','1','FrameOfReferenceUID');
$this->dict[0x0020][0x0060] = array('CS','1','Laterality');
$this->dict[0x0020][0x0062] = array('CS','1','ImageLaterality');
$this->dict[0x0020][0x0070] = array('LT','1','ImageGeometryType');
$this->dict[0x0020][0x0080] = array('CS','1-n','MaskingImage');
$this->dict[0x0020][0x0100] = array('IS','1','TemporalPositionIdentifier');
$this->dict[0x0020][0x0105] = array('IS','1','NumberOfTemporalPositions');
$this->dict[0x0020][0x0110] = array('DS','1','TemporalResolution');
$this->dict[0x0020][0x0200] = array('UI','1','SynchronizationFrameofReferenceUID');
$this->dict[0x0020][0x1000] = array('IS','1','SeriesInStudy');
$this->dict[0x0020][0x1001] = array('IS','1','AcquisitionsInSeries');
$this->dict[0x0020][0x1002] = array('IS','1','ImagesInAcquisition');
$this->dict[0x0020][0x1003] = array('IS','1','ImagesInSeries');
$this->dict[0x0020][0x1004] = array('IS','1','AcquisitionsInStudy');
$this->dict[0x0020][0x1005] = array('IS','1','ImagesInStudy');
$this->dict[0x0020][0x1020] = array('CS','1-n','Reference');
$this->dict[0x0020][0x1040] = array('LO','1','PositionReferenceIndicator');
$this->dict[0x0020][0x1041] = array('DS','1','SliceLocation');
$this->dict[0x0020][0x1070] = array('IS','1-n','OtherStudyNumbers');
$this->dict[0x0020][0x1200] = array('IS','1','NumberOfPatientRelatedStudies');
$this->dict[0x0020][0x1202] = array('IS','1','NumberOfPatientRelatedSeries');
$this->dict[0x0020][0x1204] = array('IS','1','NumberOfPatientRelatedImages');
$this->dict[0x0020][0x1206] = array('IS','1','NumberOfStudyRelatedSeries');
$this->dict[0x0020][0x1208] = array('IS','1','NumberOfStudyRelatedImages');
$this->dict[0x0020][0x1209] = array('IS','1','NumberOfSeriesRelatedInstances');
$this->dict[0x0020][0x3100] = array('CS','1-n','SourceImageID');
$this->dict[0x0020][0x3401] = array('CS','1','ModifyingDeviceID');
$this->dict[0x0020][0x3402] = array('CS','1','ModifiedImageID');
$this->dict[0x0020][0x3403] = array('DA','1','ModifiedImageDate');
$this->dict[0x0020][0x3404] = array('LO','1','ModifyingDeviceManufacturer');
$this->dict[0x0020][0x3405] = array('TM','1','ModifiedImageTime');
$this->dict[0x0020][0x3406] = array('LT','1','ModifiedImageDescription');
$this->dict[0x0020][0x4000] = array('LT','1','ImageComments');
$this->dict[0x0020][0x5000] = array('AT','1-n','OriginalImageIdentification');
$this->dict[0x0020][0x5002] = array('CS','1-n','OriginalImageIdentificationNomenclature');
$this->dict[0x0028][0x0000] = array('UL','1','ImagePresentationGroupLength');
$this->dict[0x0028][0x0002] = array('US','1','SamplesPerPixel');
$this->dict[0x0028][0x0004] = array('CS','1','PhotometricInterpretation');
$this->dict[0x0028][0x0005] = array('US','1','ImageDimensions');
$this->dict[0x0028][0x0006] = array('US','1','PlanarConfiguration');
$this->dict[0x0028][0x0008] = array('IS','1','NumberOfFrames');
$this->dict[0x0028][0x0009] = array('AT','1','FrameIncrementPointer');
$this->dict[0x0028][0x0010] = array('US','1','Rows');
$this->dict[0x0028][0x0011] = array('US','1','Columns');
$this->dict[0x0028][0x0012] = array('US','1','Planes');
$this->dict[0x0028][0x0014] = array('US','1','UltrasoundColorDataPresent');
$this->dict[0x0028][0x0030] = array('DS','2','PixelSpacing');
$this->dict[0x0028][0x0031] = array('DS','2','ZoomFactor');
$this->dict[0x0028][0x0032] = array('DS','2','ZoomCenter');
$this->dict[0x0028][0x0034] = array('IS','2','PixelAspectRatio');
$this->dict[0x0028][0x0040] = array('CS','1','ImageFormat');
$this->dict[0x0028][0x0050] = array('LT','1-n','ManipulatedImage');
$this->dict[0x0028][0x0051] = array('CS','1','CorrectedImage');
$this->dict[0x0028][0x005F] = array('CS','1','CompressionRecognitionCode');
$this->dict[0x0028][0x0060] = array('CS','1','CompressionCode');
$this->dict[0x0028][0x0061] = array('SH','1','CompressionOriginator');
$this->dict[0x0028][0x0062] = array('SH','1','CompressionLabel');
$this->dict[0x0028][0x0063] = array('SH','1','CompressionDescription');
$this->dict[0x0028][0x0065] = array('CS','1-n','CompressionSequence');
$this->dict[0x0028][0x0066] = array('AT','1-n','CompressionStepPointers');
$this->dict[0x0028][0x0068] = array('US','1','RepeatInterval');
$this->dict[0x0028][0x0069] = array('US','1','BitsGrouped');
$this->dict[0x0028][0x0070] = array('US','1-n','PerimeterTable');
$this->dict[0x0028][0x0071] = array('XS','1','PerimeterValue');
$this->dict[0x0028][0x0080] = array('US','1','PredictorRows');
$this->dict[0x0028][0x0081] = array('US','1','PredictorColumns');
$this->dict[0x0028][0x0082] = array('US','1-n','PredictorConstants');
$this->dict[0x0028][0x0090] = array('CS','1','BlockedPixels');
$this->dict[0x0028][0x0091] = array('US','1','BlockRows');
$this->dict[0x0028][0x0092] = array('US','1','BlockColumns');
$this->dict[0x0028][0x0093] = array('US','1','RowOverlap');
$this->dict[0x0028][0x0094] = array('US','1','ColumnOverlap');
$this->dict[0x0028][0x0100] = array('US','1','BitsAllocated');
$this->dict[0x0028][0x0101] = array('US','1','BitsStored');
$this->dict[0x0028][0x0102] = array('US','1','HighBit');
$this->dict[0x0028][0x0103] = array('US','1','PixelRepresentation');
$this->dict[0x0028][0x0104] = array('XS','1','SmallestValidPixelValue');
$this->dict[0x0028][0x0105] = array('XS','1','LargestValidPixelValue');
$this->dict[0x0028][0x0106] = array('XS','1','SmallestImagePixelValue');
$this->dict[0x0028][0x0107] = array('XS','1','LargestImagePixelValue');
$this->dict[0x0028][0x0108] = array('XS','1','SmallestPixelValueInSeries');
$this->dict[0x0028][0x0109] = array('XS','1','LargestPixelValueInSeries');
$this->dict[0x0028][0x0110] = array('XS','1','SmallestPixelValueInPlane');
$this->dict[0x0028][0x0111] = array('XS','1','LargestPixelValueInPlane');
$this->dict[0x0028][0x0120] = array('XS','1','PixelPaddingValue');
$this->dict[0x0028][0x0200] = array('US','1','ImageLocation');
$this->dict[0x0028][0x0300] = array('CS','1','QualityControlImage');
$this->dict[0x0028][0x0301] = array('CS','1','BurnedInAnnotation');
$this->dict[0x0028][0x0400] = array('CS','1','TransformLabel');
$this->dict[0x0028][0x0401] = array('CS','1','TransformVersionNumber');
$this->dict[0x0028][0x0402] = array('US','1','NumberOfTransformSteps');
$this->dict[0x0028][0x0403] = array('CS','1-n','SequenceOfCompressedData');
$this->dict[0x0028][0x0404] = array('AT','1-n','DetailsOfCoefficients');
$this->dict[0x0028][0x0410] = array('US','1','RowsForNthOrderCoefficients');
$this->dict[0x0028][0x0411] = array('US','1','ColumnsForNthOrderCoefficients');
$this->dict[0x0028][0x0412] = array('CS','1-n','CoefficientCoding');
$this->dict[0x0028][0x0413] = array('AT','1-n','CoefficientCodingPointers');
$this->dict[0x0028][0x0700] = array('CS','1','DCTLabel');
$this->dict[0x0028][0x0701] = array('CS','1-n','DataBlockDescription');
$this->dict[0x0028][0x0702] = array('AT','1-n','DataBlock');
$this->dict[0x0028][0x0710] = array('US','1','NormalizationFactorFormat');
$this->dict[0x0028][0x0720] = array('US','1','ZonalMapNumberFormat');
$this->dict[0x0028][0x0721] = array('AT','1-n','ZonalMapLocation');
$this->dict[0x0028][0x0722] = array('US','1','ZonalMapFormat');
$this->dict[0x0028][0x0730] = array('US','1','AdaptiveMapFormat');
$this->dict[0x0028][0x0740] = array('US','1','CodeNumberFormat');
$this->dict[0x0028][0x0800] = array('CS','1-n','CodeLabel');
$this->dict[0x0028][0x0802] = array('US','1','NumberOfTables');
$this->dict[0x0028][0x0803] = array('AT','1-n','CodeTableLocation');
$this->dict[0x0028][0x0804] = array('US','1','BitsForCodeWord');
$this->dict[0x0028][0x0808] = array('AT','1-n','ImageDataLocation');
$this->dict[0x0028][0x1040] = array('CS','1','PixelIntensityRelationship');
$this->dict[0x0028][0x1041] = array('SS','1','PixelIntensityRelationshipSign');
$this->dict[0x0028][0x1050] = array('DS','1-n','WindowCenter');
$this->dict[0x0028][0x1051] = array('DS','1-n','WindowWidth');
$this->dict[0x0028][0x1052] = array('DS','1','RescaleIntercept');
$this->dict[0x0028][0x1053] = array('DS','1','RescaleSlope');
$this->dict[0x0028][0x1054] = array('LO','1','RescaleType');
$this->dict[0x0028][0x1055] = array('LO','1-n','WindowCenterWidthExplanation');
$this->dict[0x0028][0x1080] = array('CS','1','GrayScale');
$this->dict[0x0028][0x1090] = array('CS','1','RecommendedViewingMode');
$this->dict[0x0028][0x1100] = array('XS','3','GrayLookupTableDescriptor');
$this->dict[0x0028][0x1101] = array('XS','3','RedPaletteColorLookupTableDescriptor');
$this->dict[0x0028][0x1102] = array('XS','3','GreenPaletteColorLookupTableDescriptor');
$this->dict[0x0028][0x1103] = array('XS','3','BluePaletteColorLookupTableDescriptor');
$this->dict[0x0028][0x1111] = array('US','4','LargeRedPaletteColorLookupTableDescriptor');
$this->dict[0x0028][0x1112] = array('US','4','LargeGreenPaletteColorLookupTabe');
$this->dict[0x0028][0x1113] = array('US','4','LargeBluePaletteColorLookupTabl');
$this->dict[0x0028][0x1199] = array('UI','1','PaletteColorLookupTableUID');
$this->dict[0x0028][0x1200] = array('XS','1-n','GrayLookupTableData');
$this->dict[0x0028][0x1201] = array('XS','1-n','RedPaletteColorLookupTableData');
$this->dict[0x0028][0x1202] = array('XS','1-n','GreenPaletteColorLookupTableData');
$this->dict[0x0028][0x1203] = array('XS','1-n','BluePaletteColorLookupTableData');
$this->dict[0x0028][0x1211] = array('OW','1','LargeRedPaletteColorLookupTableData');
$this->dict[0x0028][0x1212] = array('OW','1','LargeGreenPaletteColorLookupTableData');
$this->dict[0x0028][0x1213] = array('OW','1','LargeBluePaletteColorLookupTableData');
$this->dict[0x0028][0x1214] = array('UI','1','LargePaletteColorLookupTableUID');
$this->dict[0x0028][0x1221] = array('OW','1','SegmentedRedPaletteColorLookupTableData');
$this->dict[0x0028][0x1222] = array('OW','1','SegmentedGreenPaletteColorLookupTableData');
$this->dict[0x0028][0x1223] = array('OW','1','SegmentedBluePaletteColorLookupTableData');
$this->dict[0x0028][0x1300] = array('CS','1','ImplantPresent');
$this->dict[0x0028][0x2110] = array('CS','1','LossyImageCompression');
$this->dict[0x0028][0x2112] = array('DS','1-n','LossyImageCompressionRatio');
$this->dict[0x0028][0x3000] = array('SQ','1','ModalityLUTSequence');
$this->dict[0x0028][0x3002] = array('XS','3','LUTDescriptor');
$this->dict[0x0028][0x3003] = array('LO','1','LUTExplanation');
$this->dict[0x0028][0x3004] = array('LO','1','ModalityLUTType');
$this->dict[0x0028][0x3006] = array('XS','1-n','LUTData');
$this->dict[0x0028][0x3010] = array('SQ','1','VOILUTSequence');
$this->dict[0x0028][0x3110] = array('SQ','1','SoftcopyVOILUTSequence');
$this->dict[0x0028][0x4000] = array('LT','1-n','ImagePresentationComments');
$this->dict[0x0028][0x5000] = array('SQ','1','BiPlaneAcquisitionSequence');
$this->dict[0x0028][0x6010] = array('US','1','RepresentativeFrameNumber');
$this->dict[0x0028][0x6020] = array('US','1-n','FrameNumbersOfInterest');
$this->dict[0x0028][0x6022] = array('LO','1-n','FrameOfInterestDescription');
$this->dict[0x0028][0x6030] = array('US','1-n','MaskPointer');
$this->dict[0x0028][0x6040] = array('US','1-n','RWavePointer');
$this->dict[0x0028][0x6100] = array('SQ','1','MaskSubtractionSequence');
$this->dict[0x0028][0x6101] = array('CS','1','MaskOperation');
$this->dict[0x0028][0x6102] = array('US','1-n','ApplicableFrameRange');
$this->dict[0x0028][0x6110] = array('US','1-n','MaskFrameNumbers');
$this->dict[0x0028][0x6112] = array('US','1','ContrastFrameAveraging');
$this->dict[0x0028][0x6114] = array('FL','2','MaskSubPixelShift');
$this->dict[0x0028][0x6120] = array('SS','1','TIDOffset');
$this->dict[0x0028][0x6190] = array('ST','1','MaskOperationExplanation');
$this->dict[0x0032][0x0000] = array('UL','1','StudyGroupLength');
$this->dict[0x0032][0x000A] = array('CS','1','StudyStatusID');
$this->dict[0x0032][0x000C] = array('CS','1','StudyPriorityID');
$this->dict[0x0032][0x0012] = array('LO','1','StudyIDIssuer');
$this->dict[0x0032][0x0032] = array('DA','1','StudyVerifiedDate');
$this->dict[0x0032][0x0033] = array('TM','1','StudyVerifiedTime');
$this->dict[0x0032][0x0034] = array('DA','1','StudyReadDate');
$this->dict[0x0032][0x0035] = array('TM','1','StudyReadTime');
$this->dict[0x0032][0x1000] = array('DA','1','ScheduledStudyStartDate');
$this->dict[0x0032][0x1001] = array('TM','1','ScheduledStudyStartTime');
$this->dict[0x0032][0x1010] = array('DA','1','ScheduledStudyStopDate');
$this->dict[0x0032][0x1011] = array('TM','1','ScheduledStudyStopTime');
$this->dict[0x0032][0x1020] = array('LO','1','ScheduledStudyLocation');
$this->dict[0x0032][0x1021] = array('AE','1-n','ScheduledStudyLocationAETitle');
$this->dict[0x0032][0x1030] = array('LO','1','ReasonForStudy');
$this->dict[0x0032][0x1032] = array('PN','1','RequestingPhysician');
$this->dict[0x0032][0x1033] = array('LO','1','RequestingService');
$this->dict[0x0032][0x1040] = array('DA','1','StudyArrivalDate');
$this->dict[0x0032][0x1041] = array('TM','1','StudyArrivalTime');
$this->dict[0x0032][0x1050] = array('DA','1','StudyCompletionDate');
$this->dict[0x0032][0x1051] = array('TM','1','StudyCompletionTime');
$this->dict[0x0032][0x1055] = array('CS','1','StudyComponentStatusID');
$this->dict[0x0032][0x1060] = array('LO','1','RequestedProcedureDescription');
$this->dict[0x0032][0x1064] = array('SQ','1','RequestedProcedureCodeSequence');
$this->dict[0x0032][0x1070] = array('LO','1','RequestedContrastAgent');
$this->dict[0x0032][0x4000] = array('LT','1','StudyComments');
$this->dict[0x0038][0x0000] = array('UL','1','VisitGroupLength');
$this->dict[0x0038][0x0004] = array('SQ','1','ReferencedPatientAliasSequence');
$this->dict[0x0038][0x0008] = array('CS','1','VisitStatusID');
$this->dict[0x0038][0x0010] = array('LO','1','AdmissionID');
$this->dict[0x0038][0x0011] = array('LO','1','IssuerOfAdmissionID');
$this->dict[0x0038][0x0016] = array('LO','1','RouteOfAdmissions');
$this->dict[0x0038][0x001A] = array('DA','1','ScheduledAdmissionDate');
$this->dict[0x0038][0x001B] = array('TM','1','ScheduledAdmissionTime');
$this->dict[0x0038][0x001C] = array('DA','1','ScheduledDischargeDate');
$this->dict[0x0038][0x001D] = array('TM','1','ScheduledDischargeTime');
$this->dict[0x0038][0x001E] = array('LO','1','ScheduledPatientInstitutionResidence');
$this->dict[0x0038][0x0020] = array('DA','1','AdmittingDate');
$this->dict[0x0038][0x0021] = array('TM','1','AdmittingTime');
$this->dict[0x0038][0x0030] = array('DA','1','DischargeDate');
$this->dict[0x0038][0x0032] = array('TM','1','DischargeTime');
$this->dict[0x0038][0x0040] = array('LO','1','DischargeDiagnosisDescription');
$this->dict[0x0038][0x0044] = array('SQ','1','DischargeDiagnosisCodeSequence');
$this->dict[0x0038][0x0050] = array('LO','1','SpecialNeeds');
$this->dict[0x0038][0x0300] = array('LO','1','CurrentPatientLocation');
$this->dict[0x0038][0x0400] = array('LO','1','PatientInstitutionResidence');
$this->dict[0x0038][0x0500] = array('LO','1','PatientState');
$this->dict[0x0038][0x4000] = array('LT','1','VisitComments');
$this->dict[0x003A][0x0004] = array('CS','1','WaveformOriginality');
$this->dict[0x003A][0x0005] = array('US','1','NumberofChannels');
$this->dict[0x003A][0x0010] = array('UL','1','NumberofSamples');
$this->dict[0x003A][0x001A] = array('DS','1','SamplingFrequency');
$this->dict[0x003A][0x0020] = array('SH','1','MultiplexGroupLabel');
$this->dict[0x003A][0x0200] = array('SQ','1','ChannelDefinitionSequence');
$this->dict[0x003A][0x0202] = array('IS','1','WVChannelNumber');
$this->dict[0x003A][0x0203] = array('SH','1','ChannelLabel');
$this->dict[0x003A][0x0205] = array('CS','1-n','ChannelStatus');
$this->dict[0x003A][0x0208] = array('SQ','1','ChannelSourceSequence');
$this->dict[0x003A][0x0209] = array('SQ','1','ChannelSourceModifiersSequence');
$this->dict[0x003A][0x020A] = array('SQ','1','SourceWaveformSequence');
$this->dict[0x003A][0x020C] = array('LO','1','ChannelDerivationDescription');
$this->dict[0x003A][0x0210] = array('DS','1','ChannelSensitivity');
$this->dict[0x003A][0x0211] = array('SQ','1','ChannelSensitivityUnits');
$this->dict[0x003A][0x0212] = array('DS','1','ChannelSensitivityCorrectionFactor');
$this->dict[0x003A][0x0213] = array('DS','1','ChannelBaseline');
$this->dict[0x003A][0x0214] = array('DS','1','ChannelTimeSkew');
$this->dict[0x003A][0x0215] = array('DS','1','ChannelSampleSkew');
$this->dict[0x003A][0x0218] = array('DS','1','ChannelOffset');
$this->dict[0x003A][0x021A] = array('US','1','WaveformBitsStored');
$this->dict[0x003A][0x0220] = array('DS','1','FilterLowFrequency');
$this->dict[0x003A][0x0221] = array('DS','1','FilterHighFrequency');
$this->dict[0x003A][0x0222] = array('DS','1','NotchFilterFrequency');
$this->dict[0x003A][0x0223] = array('DS','1','NotchFilterBandwidth');
$this->dict[0x0040][0x0000] = array('UL','1','ModalityWorklistGroupLength');
$this->dict[0x0040][0x0001] = array('AE','1','ScheduledStationAETitle');
$this->dict[0x0040][0x0002] = array('DA','1','ScheduledProcedureStepStartDate');
$this->dict[0x0040][0x0003] = array('TM','1','ScheduledProcedureStepStartTime');
$this->dict[0x0040][0x0004] = array('DA','1','ScheduledProcedureStepEndDate');
$this->dict[0x0040][0x0005] = array('TM','1','ScheduledProcedureStepEndTime');
$this->dict[0x0040][0x0006] = array('PN','1','ScheduledPerformingPhysicianName');
$this->dict[0x0040][0x0007] = array('LO','1','ScheduledProcedureStepDescription');
$this->dict[0x0040][0x0008] = array('SQ','1','ScheduledProcedureStepCodeSequence');
$this->dict[0x0040][0x0009] = array('SH','1','ScheduledProcedureStepID');
$this->dict[0x0040][0x0010] = array('SH','1','ScheduledStationName');
$this->dict[0x0040][0x0011] = array('SH','1','ScheduledProcedureStepLocation');
$this->dict[0x0040][0x0012] = array('LO','1','ScheduledPreOrderOfMedication');
$this->dict[0x0040][0x0020] = array('CS','1','ScheduledProcedureStepStatus');
$this->dict[0x0040][0x0100] = array('SQ','1-n','ScheduledProcedureStepSequence');
$this->dict[0x0040][0x0220] = array('SQ','1','ReferencedStandaloneSOPInstanceSequence');
$this->dict[0x0040][0x0241] = array('AE','1','PerformedStationAETitle');
$this->dict[0x0040][0x0242] = array('SH','1','PerformedStationName');
$this->dict[0x0040][0x0243] = array('SH','1','PerformedLocation');
$this->dict[0x0040][0x0244] = array('DA','1','PerformedProcedureStepStartDate');
$this->dict[0x0040][0x0245] = array('TM','1','PerformedProcedureStepStartTime');
$this->dict[0x0040][0x0250] = array('DA','1','PerformedProcedureStepEndDate');
$this->dict[0x0040][0x0251] = array('TM','1','PerformedProcedureStepEndTime');
$this->dict[0x0040][0x0252] = array('CS','1','PerformedProcedureStepStatus');
$this->dict[0x0040][0x0253] = array('CS','1','PerformedProcedureStepID');
$this->dict[0x0040][0x0254] = array('LO','1','PerformedProcedureStepDescription');
$this->dict[0x0040][0x0255] = array('LO','1','PerformedProcedureTypeDescription');
$this->dict[0x0040][0x0260] = array('SQ','1','PerformedActionItemSequence');
$this->dict[0x0040][0x0270] = array('SQ','1','ScheduledStepAttributesSequence');
$this->dict[0x0040][0x0275] = array('SQ','1','RequestAttributesSequence');
$this->dict[0x0040][0x0280] = array('ST','1','CommentsOnThePerformedProcedureSteps');
$this->dict[0x0040][0x0293] = array('SQ','1','QuantitySequence');
$this->dict[0x0040][0x0294] = array('DS','1','Quantity');
$this->dict[0x0040][0x0295] = array('SQ','1','MeasuringUnitsSequence');
$this->dict[0x0040][0x0296] = array('SQ','1','BillingItemSequence');
$this->dict[0x0040][0x0300] = array('US','1','TotalTimeOfFluoroscopy');
$this->dict[0x0040][0x0301] = array('US','1','TotalNumberOfExposures');
$this->dict[0x0040][0x0302] = array('US','1','EntranceDose');
$this->dict[0x0040][0x0303] = array('US','1-2','ExposedArea');
$this->dict[0x0040][0x0306] = array('DS','1','DistanceSourceToEntrance');
$this->dict[0x0040][0x0307] = array('DS','1','DistanceSourceToSupport');
$this->dict[0x0040][0x0310] = array('ST','1','CommentsOnRadiationDose');
$this->dict[0x0040][0x0312] = array('DS','1','XRayOutput');
$this->dict[0x0040][0x0314] = array('DS','1','HalfValueLayer');
$this->dict[0x0040][0x0316] = array('DS','1','OrganDose');
$this->dict[0x0040][0x0318] = array('CS','1','OrganExposed');
$this->dict[0x0040][0x0320] = array('SQ','1','BillingProcedureStepSequence');
$this->dict[0x0040][0x0321] = array('SQ','1','FilmConsumptionSequence');
$this->dict[0x0040][0x0324] = array('SQ','1','BillingSuppliesAndDevicesSequence');
$this->dict[0x0040][0x0330] = array('SQ','1','ReferencedProcedureStepSequence');
$this->dict[0x0040][0x0340] = array('SQ','1','PerformedSeriesSequence');
$this->dict[0x0040][0x0400] = array('LT','1','CommentsOnScheduledProcedureStep');
$this->dict[0x0040][0x050A] = array('LO','1','SpecimenAccessionNumber');
$this->dict[0x0040][0x0550] = array('SQ','1','SpecimenSequence');
$this->dict[0x0040][0x0551] = array('LO','1','SpecimenIdentifier');
$this->dict[0x0040][0x0555] = array('SQ','1','AcquisitionContextSequence');
$this->dict[0x0040][0x0556] = array('ST','1','AcquisitionContextDescription');
$this->dict[0x0040][0x059A] = array('SQ','1','SpecimenTypeCodeSequence');
$this->dict[0x0040][0x06FA] = array('LO','1','SlideIdentifier');
$this->dict[0x0040][0x071A] = array('SQ','1','ImageCenterPointCoordinatesSequence');
$this->dict[0x0040][0x072A] = array('DS','1','XOffsetInSlideCoordinateSystem');
$this->dict[0x0040][0x073A] = array('DS','1','YOffsetInSlideCoordinateSystem');
$this->dict[0x0040][0x074A] = array('DS','1','ZOffsetInSlideCoordinateSystem');
$this->dict[0x0040][0x08D8] = array('SQ','1','PixelSpacingSequence');
$this->dict[0x0040][0x08DA] = array('SQ','1','CoordinateSystemAxisCodeSequence');
$this->dict[0x0040][0x08EA] = array('SQ','1','MeasurementUnitsCodeSequence');
$this->dict[0x0040][0x1001] = array('SH','1','RequestedProcedureID');
$this->dict[0x0040][0x1002] = array('LO','1','ReasonForRequestedProcedure');
$this->dict[0x0040][0x1003] = array('SH','1','RequestedProcedurePriority');
$this->dict[0x0040][0x1004] = array('LO','1','PatientTransportArrangements');
$this->dict[0x0040][0x1005] = array('LO','1','RequestedProcedureLocation');
$this->dict[0x0040][0x1006] = array('SH','1','PlacerOrderNumberOfProcedure');
$this->dict[0x0040][0x1007] = array('SH','1','FillerOrderNumberOfProcedure');
$this->dict[0x0040][0x1008] = array('LO','1','ConfidentialityCode');
$this->dict[0x0040][0x1009] = array('SH','1','ReportingPriority');
$this->dict[0x0040][0x1010] = array('PN','1-n','NamesOfIntendedRecipientsOfResults');
$this->dict[0x0040][0x1400] = array('LT','1','RequestedProcedureComments');
$this->dict[0x0040][0x2001] = array('LO','1','ReasonForTheImagingServiceRequest');
$this->dict[0x0040][0x2002] = array('LO','1','ImagingServiceRequestDescription');
$this->dict[0x0040][0x2004] = array('DA','1','IssueDateOfImagingServiceRequest');
$this->dict[0x0040][0x2005] = array('TM','1','IssueTimeOfImagingServiceRequest');
$this->dict[0x0040][0x2006] = array('SH','1','PlacerOrderNumberOfImagingServiceRequest');
$this->dict[0x0040][0x2007] = array('SH','0','FillerOrderNumberOfImagingServiceRequest');
$this->dict[0x0040][0x2008] = array('PN','1','OrderEnteredBy');
$this->dict[0x0040][0x2009] = array('SH','1','OrderEntererLocation');
$this->dict[0x0040][0x2010] = array('SH','1','OrderCallbackPhoneNumber');
$this->dict[0x0040][0x2016] = array('LO','1','PlacerOrderNumberImagingServiceRequest');
$this->dict[0x0040][0x2017] = array('LO','1','FillerOrderNumberImagingServiceRequest');
$this->dict[0x0040][0x2400] = array('LT','1','ImagingServiceRequestComments');
$this->dict[0x0040][0x3001] = array('LT','1','ConfidentialityConstraint');
$this->dict[0x0040][0xA010] = array('CS','1','RelationshipType');
$this->dict[0x0040][0xA027] = array('LO','1','VerifyingOrganization');
$this->dict[0x0040][0xA030] = array('DT','1','VerificationDateTime');
$this->dict[0x0040][0xA032] = array('DT','1','ObservationDateTime');
$this->dict[0x0040][0xA040] = array('CS','1','ValueType');
$this->dict[0x0040][0xA043] = array('SQ','1','ConceptNameCodeSequence');
$this->dict[0x0040][0xA050] = array('CS','1','ContinuityOfContent');
$this->dict[0x0040][0xA073] = array('SQ','1','VerifyingObserverSequence');
$this->dict[0x0040][0xA075] = array('PN','1','VerifyingObserverName');
$this->dict[0x0040][0xA088] = array('SQ','1','VerifyingObserverIdentificationCodeSeque');
$this->dict[0x0040][0xA0B0] = array('US','2-2n','ReferencedWaveformChannels');
$this->dict[0x0040][0xA120] = array('DT','1','DateTime');
$this->dict[0x0040][0xA121] = array('DA','1','Date');
$this->dict[0x0040][0xA122] = array('TM','1','Time');
$this->dict[0x0040][0xA123] = array('PN','1','PersonName');
$this->dict[0x0040][0xA124] = array('UI','1','UID');
$this->dict[0x0040][0xA130] = array('CS','1','TemporalRangeType');
$this->dict[0x0040][0xA132] = array('UL','1-n','ReferencedSamplePositionsU');
$this->dict[0x0040][0xA136] = array('US','1-n','ReferencedFrameNumbers');
$this->dict[0x0040][0xA138] = array('DS','1-n','ReferencedTimeOffsets');
$this->dict[0x0040][0xA13A] = array('DT','1-n','ReferencedDatetime');
$this->dict[0x0040][0xA160] = array('UT','1','TextValue');
$this->dict[0x0040][0xA168] = array('SQ','1','ConceptCodeSequence');
$this->dict[0x0040][0xA180] = array('US','1','AnnotationGroupNumber');
$this->dict[0x0040][0xA195] = array('SQ','1','ConceptNameCodeSequenceModifier');
$this->dict[0x0040][0xA300] = array('SQ','1','MeasuredValueSequence');
$this->dict[0x0040][0xA30A] = array('DS','1-n','NumericValue');
$this->dict[0x0040][0xA360] = array('SQ','1','PredecessorDocumentsSequence');
$this->dict[0x0040][0xA370] = array('SQ','1','ReferencedRequestSequence');
$this->dict[0x0040][0xA372] = array('SQ','1','PerformedProcedureCodeSequence');
$this->dict[0x0040][0xA375] = array('SQ','1','CurrentRequestedProcedureEvidenceSequenSequence');
$this->dict[0x0040][0xA385] = array('SQ','1','PertinentOtherEvidenceSequence');
$this->dict[0x0040][0xA491] = array('CS','1','CompletionFlag');
$this->dict[0x0040][0xA492] = array('LO','1','CompletionFlagDescription');
$this->dict[0x0040][0xA493] = array('CS','1','VerificationFlag');
$this->dict[0x0040][0xA504] = array('SQ','1','ContentTemplateSequence');
$this->dict[0x0040][0xA525] = array('SQ','1','IdenticalDocumentsSequence');
$this->dict[0x0040][0xA730] = array('SQ','1','ContentSequence');
$this->dict[0x0040][0xB020] = array('SQ','1','AnnotationSequence');
$this->dict[0x0040][0xDB00] = array('CS','1','TemplateIdentifier');
$this->dict[0x0040][0xDB06] = array('DT','1','TemplateVersion');
$this->dict[0x0040][0xDB07] = array('DT','1','TemplateLocalVersion');
$this->dict[0x0040][0xDB0B] = array('CS','1','TemplateExtensionFlag');
$this->dict[0x0040][0xDB0C] = array('UI','1','TemplateExtensionOrganizationUID');
$this->dict[0x0040][0xDB0D] = array('UI','1','TemplateExtensionCreatorUID');
$this->dict[0x0040][0xDB73] = array('UL','1-n','ReferencedContentItemIdentifier');
$this->dict[0x0050][0x0000] = array('UL','1','XRayAngioDeviceGroupLength');
$this->dict[0x0050][0x0004] = array('CS','1','CalibrationObject');
$this->dict[0x0050][0x0010] = array('SQ','1','DeviceSequence');
$this->dict[0x0050][0x0012] = array('CS','1','DeviceType');
$this->dict[0x0050][0x0014] = array('DS','1','DeviceLength');
$this->dict[0x0050][0x0016] = array('DS','1','DeviceDiameter');
$this->dict[0x0050][0x0017] = array('CS','1','DeviceDiameterUnits');
$this->dict[0x0050][0x0018] = array('DS','1','DeviceVolume');
$this->dict[0x0050][0x0019] = array('DS','1','InterMarkerDistance');
$this->dict[0x0050][0x0020] = array('LO','1','DeviceDescription');
$this->dict[0x0050][0x0030] = array('SQ','1','CodedInterventionalDeviceSequence');
$this->dict[0x0054][0x0000] = array('UL','1','NuclearMedicineGroupLength');
$this->dict[0x0054][0x0010] = array('US','1-n','EnergyWindowVector');
$this->dict[0x0054][0x0011] = array('US','1','NumberOfEnergyWindows');
$this->dict[0x0054][0x0012] = array('SQ','1','EnergyWindowInformationSequence');
$this->dict[0x0054][0x0013] = array('SQ','1','EnergyWindowRangeSequence');
$this->dict[0x0054][0x0014] = array('DS','1','EnergyWindowLowerLimit');
$this->dict[0x0054][0x0015] = array('DS','1','EnergyWindowUpperLimit');
$this->dict[0x0054][0x0016] = array('SQ','1','RadiopharmaceuticalInformationSequence');
$this->dict[0x0054][0x0017] = array('IS','1','ResidualSyringeCounts');
$this->dict[0x0054][0x0018] = array('SH','1','EnergyWindowName');
$this->dict[0x0054][0x0020] = array('US','1-n','DetectorVector');
$this->dict[0x0054][0x0021] = array('US','1','NumberOfDetectors');
$this->dict[0x0054][0x0022] = array('SQ','1','DetectorInformationSequence');
$this->dict[0x0054][0x0030] = array('US','1-n','PhaseVector');
$this->dict[0x0054][0x0031] = array('US','1','NumberOfPhases');
$this->dict[0x0054][0x0032] = array('SQ','1','PhaseInformationSequence');
$this->dict[0x0054][0x0033] = array('US','1','NumberOfFramesInPhase');
$this->dict[0x0054][0x0036] = array('IS','1','PhaseDelay');
$this->dict[0x0054][0x0038] = array('IS','1','PauseBetweenFrames');
$this->dict[0x0054][0x0050] = array('US','1-n','RotationVector');
$this->dict[0x0054][0x0051] = array('US','1','NumberOfRotations');
$this->dict[0x0054][0x0052] = array('SQ','1','RotationInformationSequence');
$this->dict[0x0054][0x0053] = array('US','1','NumberOfFramesInRotation');
$this->dict[0x0054][0x0060] = array('US','1-n','RRIntervalVector');
$this->dict[0x0054][0x0061] = array('US','1','NumberOfRRIntervals');
$this->dict[0x0054][0x0062] = array('SQ','1','GatedInformationSequence');
$this->dict[0x0054][0x0063] = array('SQ','1','DataInformationSequence');
$this->dict[0x0054][0x0070] = array('US','1-n','TimeSlotVector');
$this->dict[0x0054][0x0071] = array('US','1','NumberOfTimeSlots');
$this->dict[0x0054][0x0072] = array('SQ','1','TimeSlotInformationSequence');
$this->dict[0x0054][0x0073] = array('DS','1','TimeSlotTime');
$this->dict[0x0054][0x0080] = array('US','1-n','SliceVector');
$this->dict[0x0054][0x0081] = array('US','1','NumberOfSlices');
$this->dict[0x0054][0x0090] = array('US','1-n','AngularViewVector');
$this->dict[0x0054][0x0100] = array('US','1-n','TimeSliceVector');
$this->dict[0x0054][0x0101] = array('US','1','NumberOfTimeSlices');
$this->dict[0x0054][0x0200] = array('DS','1','StartAngle');
$this->dict[0x0054][0x0202] = array('CS','1','TypeOfDetectorMotion');
$this->dict[0x0054][0x0210] = array('IS','1-n','TriggerVector');
$this->dict[0x0054][0x0211] = array('US','1','NumberOfTriggersInPhase');
$this->dict[0x0054][0x0220] = array('SQ','1','ViewCodeSequence');
$this->dict[0x0054][0x0222] = array('SQ','1','ViewAngulationModifierCodeSequence');
$this->dict[0x0054][0x0300] = array('SQ','1','RadionuclideCodeSequence');
$this->dict[0x0054][0x0302] = array('SQ','1','AdministrationRouteCodeSequence');
$this->dict[0x0054][0x0304] = array('SQ','1','RadiopharmaceuticalCodeSequence');
$this->dict[0x0054][0x0306] = array('SQ','1','CalibrationDataSequence');
$this->dict[0x0054][0x0308] = array('US','1','EnergyWindowNumber');
$this->dict[0x0054][0x0400] = array('SH','1','ImageID');
$this->dict[0x0054][0x0410] = array('SQ','1','PatientOrientationCodeSequence');
$this->dict[0x0054][0x0412] = array('SQ','1','PatientOrientationModifierCodeSequence');
$this->dict[0x0054][0x0414] = array('SQ','1','PatientGantryRelationshipCodeSequence');
$this->dict[0x0054][0x1000] = array('CS','2','SeriesType');
$this->dict[0x0054][0x1001] = array('CS','1','Units');
$this->dict[0x0054][0x1002] = array('CS','1','CountsSource');
$this->dict[0x0054][0x1004] = array('CS','1','ReprojectionMethod');
$this->dict[0x0054][0x1100] = array('CS','1','RandomsCorrectionMethod');
$this->dict[0x0054][0x1101] = array('LO','1','AttenuationCorrectionMethod');
$this->dict[0x0054][0x1102] = array('CS','1','DecayCorrection');
$this->dict[0x0054][0x1103] = array('LO','1','ReconstructionMethod');
$this->dict[0x0054][0x1104] = array('LO','1','DetectorLinesOfResponseUsed');
$this->dict[0x0054][0x1105] = array('LO','1','ScatterCorrectionMethod');
$this->dict[0x0054][0x1200] = array('DS','1','AxialAcceptance');
$this->dict[0x0054][0x1201] = array('IS','2','AxialMash');
$this->dict[0x0054][0x1202] = array('IS','1','TransverseMash');
$this->dict[0x0054][0x1203] = array('DS','2','DetectorElementSize');
$this->dict[0x0054][0x1210] = array('DS','1','CoincidenceWindowWidth');
$this->dict[0x0054][0x1220] = array('CS','1-n','SecondaryCountsType');
$this->dict[0x0054][0x1300] = array('DS','1','FrameReferenceTime');
$this->dict[0x0054][0x1310] = array('IS','1','PrimaryPromptsCountsAccumulated');
$this->dict[0x0054][0x1311] = array('IS','1-n','SecondaryCountsAccumulated');
$this->dict[0x0054][0x1320] = array('DS','1','SliceSensitivityFactor');
$this->dict[0x0054][0x1321] = array('DS','1','DecayFactor');
$this->dict[0x0054][0x1322] = array('DS','1','DoseCalibrationFactor');
$this->dict[0x0054][0x1323] = array('DS','1','ScatterFractionFactor');
$this->dict[0x0054][0x1324] = array('DS','1','DeadTimeFactor');
$this->dict[0x0054][0x1330] = array('US','1','ImageIndex');
$this->dict[0x0054][0x1400] = array('CS','1-n','CountsIncluded');
$this->dict[0x0054][0x1401] = array('CS','1','DeadTimeCorrectionFlag');
$this->dict[0x0060][0x0000] = array('UL','1','HistogramGroupLength');
$this->dict[0x0060][0x3000] = array('SQ','1','HistogramSequence');
$this->dict[0x0060][0x3002] = array('US','1','HistogramNumberofBins');
$this->dict[0x0060][0x3004] = array('US/SS','1','HistogramFirstBinValue');
$this->dict[0x0060][0x3006] = array('US/SS','1','HistogramLastBinValue');
$this->dict[0x0060][0x3008] = array('US','1','HistogramBinWidth');
$this->dict[0x0060][0x3010] = array('LO','1','HistogramExplanation');
$this->dict[0x0060][0x3020] = array('UL','1-n','HistogramData');
$this->dict[0x0070][0x0001] = array('SQ','1','GraphicAnnotationSequence');
$this->dict[0x0070][0x0002] = array('CS','1','GraphicLayer');
$this->dict[0x0070][0x0003] = array('CS','1','BoundingBoxAnnotationUnits');
$this->dict[0x0070][0x0004] = array('CS','1','AnchorPointAnnotationUnits');
$this->dict[0x0070][0x0005] = array('CS','1','GraphicAnnotationUnits');
$this->dict[0x0070][0x0006] = array('ST','1','UnformattedTextValue');
$this->dict[0x0070][0x0008] = array('SQ','1','TextObjectSequence');
$this->dict[0x0070][0x0009] = array('SQ','1','GraphicObjectSequence');
$this->dict[0x0070][0x0010] = array('FL','2','BoundingBoxTopLeftHandCorner');
$this->dict[0x0070][0x0011] = array('FL','2','BoundingBoxBottomRightHandCorner');
$this->dict[0x0070][0x0012] = array('CS','1','BoundingBoxTextHorizontalJustification');
$this->dict[0x0070][0x0014] = array('FL','2','AnchorPoint');
$this->dict[0x0070][0x0015] = array('CS','1','AnchorPointVisibility');
$this->dict[0x0070][0x0020] = array('US','1','GraphicDimensions');
$this->dict[0x0070][0x0021] = array('US','1','NumberOfGraphicPoints');
$this->dict[0x0070][0x0022] = array('FL','2-n','GraphicData');
$this->dict[0x0070][0x0023] = array('CS','1','GraphicType');
$this->dict[0x0070][0x0024] = array('CS','1','GraphicFilled');
$this->dict[0x0070][0x0040] = array('IS','1','ImageRotationFrozenDraftRetired');
$this->dict[0x0070][0x0041] = array('CS','1','ImageHorizontalFlip');
$this->dict[0x0070][0x0042] = array('US','1','ImageRotation');
$this->dict[0x0070][0x0050] = array('US','2','DisplayedAreaTLHCFrozenDraftRetired');
$this->dict[0x0070][0x0051] = array('US','2','DisplayedAreaBRHCFrozenDraftRetired');
$this->dict[0x0070][0x0052] = array('SL','2','DisplayedAreaTopLeftHandCorner');
$this->dict[0x0070][0x0053] = array('SL','2','DisplayedAreaBottomRightHandCorner');
$this->dict[0x0070][0x005A] = array('SQ','1','DisplayedAreaSelectionSequence');
$this->dict[0x0070][0x0060] = array('SQ','1','GraphicLayerSequence');
$this->dict[0x0070][0x0062] = array('IS','1','GraphicLayerOrder');
$this->dict[0x0070][0x0066] = array('US','1','GraphicLayerRecommendedDisplayGrayscaleValue');
$this->dict[0x0070][0x0067] = array('US','3','GraphicLayerRecommendedDisplayRGBValue');
$this->dict[0x0070][0x0068] = array('LO','1','GraphicLayerDescription');
$this->dict[0x0070][0x0080] = array('CS','1','PresentationLabel');
$this->dict[0x0070][0x0081] = array('LO','1','PresentationDescription');
$this->dict[0x0070][0x0082] = array('DA','1','PresentationCreationDate');
$this->dict[0x0070][0x0083] = array('TM','1','PresentationCreationTime');
$this->dict[0x0070][0x0084] = array('PN','1','PresentationCreatorsName');
$this->dict[0x0070][0x0100] = array('CS','1','PresentationSizeMode');
$this->dict[0x0070][0x0101] = array('DS','2','PresentationPixelSpacing');
$this->dict[0x0070][0x0102] = array('IS','2','PresentationPixelAspectRatio');
$this->dict[0x0070][0x0103] = array('FL','1','PresentationPixelMagnificationRatio');
$this->dict[0x0088][0x0000] = array('UL','1','StorageGroupLength');
$this->dict[0x0088][0x0130] = array('SH','1','StorageMediaFilesetID');
$this->dict[0x0088][0x0140] = array('UI','1','StorageMediaFilesetUID');
$this->dict[0x0088][0x0200] = array('SQ','1','IconImage');
$this->dict[0x0088][0x0904] = array('LO','1','TopicTitle');
$this->dict[0x0088][0x0906] = array('ST','1','TopicSubject');
$this->dict[0x0088][0x0910] = array('LO','1','TopicAuthor');
$this->dict[0x0088][0x0912] = array('LO','3','TopicKeyWords');
$this->dict[0x1000][0x0000] = array('UL','1','CodeTableGroupLength');
$this->dict[0x1000][0x0010] = array('US','3','EscapeTriplet');
$this->dict[0x1000][0x0011] = array('US','3','RunLengthTriplet');
$this->dict[0x1000][0x0012] = array('US','1','HuffmanTableSize');
$this->dict[0x1000][0x0013] = array('US','3','HuffmanTableTriplet');
$this->dict[0x1000][0x0014] = array('US','1','ShiftTableSize');
$this->dict[0x1000][0x0015] = array('US','3','ShiftTableTriplet');
$this->dict[0x1010][0x0000] = array('UL','1','ZonalMapGroupLength');
$this->dict[0x1010][0x0004] = array('US','1-n','ZonalMap');
$this->dict[0x2000][0x0000] = array('UL','1','FilmSessionGroupLength');
$this->dict[0x2000][0x0010] = array('IS','1','NumberOfCopies');
$this->dict[0x2000][0x001E] = array('SQ','1','PrinterConfigurationSequence');
$this->dict[0x2000][0x0020] = array('CS','1','PrintPriority');
$this->dict[0x2000][0x0030] = array('CS','1','MediumType');
$this->dict[0x2000][0x0040] = array('CS','1','FilmDestination');
$this->dict[0x2000][0x0050] = array('LO','1','FilmSessionLabel');
$this->dict[0x2000][0x0060] = array('IS','1','MemoryAllocation');
$this->dict[0x2000][0x0061] = array('IS','1','MaximumMemoryAllocation');
$this->dict[0x2000][0x0062] = array('CS','1','ColorImagePrintingFlag');
$this->dict[0x2000][0x0063] = array('CS','1','CollationFlag');
$this->dict[0x2000][0x0065] = array('CS','1','AnnotationFlag');
$this->dict[0x2000][0x0067] = array('CS','1','ImageOverlayFlag');
$this->dict[0x2000][0x0069] = array('CS','1','PresentationLUTFlag');
$this->dict[0x2000][0x006A] = array('CS','1','ImageBoxPresentationLUTFlag');
$this->dict[0x2000][0x00A0] = array('US','1','MemoryBitDepth');
$this->dict[0x2000][0x00A1] = array('US','1','PrintingBitDepth');
$this->dict[0x2000][0x00A2] = array('SQ','1','MediaInstalledSequence');
$this->dict[0x2000][0x00A4] = array('SQ','1','OtherMediaAvailableSequence');
$this->dict[0x2000][0x00A8] = array('SQ','1','SupportedImageDisplayFormatsSequence');
$this->dict[0x2000][0x0500] = array('SQ','1','ReferencedFilmBoxSequence');
$this->dict[0x2000][0x0510] = array('SQ','1','ReferencedStoredPrintSequence');
$this->dict[0x2010][0x0000] = array('UL','1','FilmBoxGroupLength');
$this->dict[0x2010][0x0010] = array('ST','1','ImageDisplayFormat');
$this->dict[0x2010][0x0030] = array('CS','1','AnnotationDisplayFormatID');
$this->dict[0x2010][0x0040] = array('CS','1','FilmOrientation');
$this->dict[0x2010][0x0050] = array('CS','1','FilmSizeID');
$this->dict[0x2010][0x0052] = array('CS','1','PrinterResolutionID');
$this->dict[0x2010][0x0054] = array('CS','1','DefaultPrinterResolutionID');
$this->dict[0x2010][0x0060] = array('CS','1','MagnificationType');
$this->dict[0x2010][0x0080] = array('CS','1','SmoothingType');
$this->dict[0x2010][0x00A6] = array('CS','1','DefaultMagnificationType');
$this->dict[0x2010][0x00A7] = array('CS','1-n','OtherMagnificationTypesAvailable');
$this->dict[0x2010][0x00A8] = array('CS','1','DefaultSmoothingType');
$this->dict[0x2010][0x00A9] = array('CS','1-n','OtherSmoothingTypesAvailable');
$this->dict[0x2010][0x0100] = array('CS','1','BorderDensity');
$this->dict[0x2010][0x0110] = array('CS','1','EmptyImageDensity');
$this->dict[0x2010][0x0120] = array('US','1','MinDensity');
$this->dict[0x2010][0x0130] = array('US','1','MaxDensity');
$this->dict[0x2010][0x0140] = array('CS','1','Trim');
$this->dict[0x2010][0x0150] = array('ST','1','ConfigurationInformation');
$this->dict[0x2010][0x0152] = array('LT','1','ConfigurationInformationDescription');
$this->dict[0x2010][0x0154] = array('IS','1','MaximumCollatedFilms');
$this->dict[0x2010][0x015E] = array('US','1','Illumination');
$this->dict[0x2010][0x0160] = array('US','1','ReflectedAmbientLight');
$this->dict[0x2010][0x0376] = array('DS','2','PrinterPixelSpacing');
$this->dict[0x2010][0x0500] = array('SQ','1','ReferencedFilmSessionSequence');
$this->dict[0x2010][0x0510] = array('SQ','1','ReferencedImageBoxSequence');
$this->dict[0x2010][0x0520] = array('SQ','1','ReferencedBasicAnnotationBoxSequence');
$this->dict[0x2020][0x0000] = array('UL','1','ImageBoxGroupLength');
$this->dict[0x2020][0x0010] = array('US','1','ImageBoxPosition');
$this->dict[0x2020][0x0020] = array('CS','1','Polarity');
$this->dict[0x2020][0x0030] = array('DS','1','RequestedImageSize');
$this->dict[0x2020][0x0040] = array('CS','1','RequestedDecimateCropBehavior');
$this->dict[0x2020][0x0050] = array('CS','1','RequestedResolutionID');
$this->dict[0x2020][0x00A0] = array('CS','1','RequestedImageSizeFlag');
$this->dict[0x2020][0x00A2] = array('CS','1','DecimateCropResult');
$this->dict[0x2020][0x0110] = array('SQ','1','PreformattedGrayscaleImageSequence');
$this->dict[0x2020][0x0111] = array('SQ','1','PreformattedColorImageSequence');
$this->dict[0x2020][0x0130] = array('SQ','1','ReferencedImageOverlayBoxSequence');
$this->dict[0x2020][0x0140] = array('SQ','1','ReferencedVOILUTBoxSequence');
$this->dict[0x2030][0x0000] = array('UL','1','AnnotationGroupLength');
$this->dict[0x2030][0x0010] = array('US','1','AnnotationPosition');
$this->dict[0x2030][0x0020] = array('LO','1','TextString');
$this->dict[0x2040][0x0000] = array('UL','1','OverlayBoxGroupLength');
$this->dict[0x2040][0x0010] = array('SQ','1','ReferencedOverlayPlaneSequence');
$this->dict[0x2040][0x0011] = array('US','9','ReferencedOverlayPlaneGroups');
$this->dict[0x2040][0x0020] = array('SQ','1','OverlayPixelDataSequence');
$this->dict[0x2040][0x0060] = array('CS','1','OverlayMagnificationType');
$this->dict[0x2040][0x0070] = array('CS','1','OverlaySmoothingType');
$this->dict[0x2040][0x0072] = array('CS','1','OverlayOrImageMagnification');
$this->dict[0x2040][0x0074] = array('US','1','MagnifyToNumberOfColumns');
$this->dict[0x2040][0x0080] = array('CS','1','OverlayForegroundDensity');
$this->dict[0x2040][0x0082] = array('CS','1','OverlayBackgroundDensity');
$this->dict[0x2040][0x0090] = array('CS','1','OverlayMode');
$this->dict[0x2040][0x0100] = array('CS','1','ThresholdDensity');
$this->dict[0x2040][0x0500] = array('SQ','1','ReferencedOverlayImageBoxSequence');
$this->dict[0x2050][0x0000] = array('UL','1','PresentationLUTGroupLength');
$this->dict[0x2050][0x0010] = array('SQ','1','PresentationLUTSequence');
$this->dict[0x2050][0x0020] = array('CS','1','PresentationLUTShape');
$this->dict[0x2050][0x0500] = array('SQ','1','ReferencedPresentationLUTSequence');
$this->dict[0x2100][0x0000] = array('UL','1','PrintJobGroupLength');
$this->dict[0x2100][0x0010] = array('SH','1','PrintJobID');
$this->dict[0x2100][0x0020] = array('CS','1','ExecutionStatus');
$this->dict[0x2100][0x0030] = array('CS','1','ExecutionStatusInfo');
$this->dict[0x2100][0x0040] = array('DA','1','CreationDate');
$this->dict[0x2100][0x0050] = array('TM','1','CreationTime');
$this->dict[0x2100][0x0070] = array('AE','1','Originator');
$this->dict[0x2100][0x0140] = array('AE','1','DestinationAE');
$this->dict[0x2100][0x0160] = array('SH','1','OwnerID');
$this->dict[0x2100][0x0170] = array('IS','1','NumberOfFilms');
$this->dict[0x2100][0x0500] = array('SQ','1','ReferencedPrintJobSequence');
$this->dict[0x2110][0x0000] = array('UL','1','PrinterGroupLength');
$this->dict[0x2110][0x0010] = array('CS','1','PrinterStatus');
$this->dict[0x2110][0x0020] = array('CS','1','PrinterStatusInfo');
$this->dict[0x2110][0x0030] = array('LO','1','PrinterName');
$this->dict[0x2110][0x0099] = array('SH','1','PrintQueueID');
$this->dict[0x2120][0x0000] = array('UL','1','QueueGroupLength');
$this->dict[0x2120][0x0010] = array('CS','1','QueueStatus');
$this->dict[0x2120][0x0050] = array('SQ','1','PrintJobDescriptionSequence');
$this->dict[0x2120][0x0070] = array('SQ','1','QueueReferencedPrintJobSequence');
$this->dict[0x2130][0x0000] = array('UL','1','PrintContentGroupLength');
$this->dict[0x2130][0x0010] = array('SQ','1','PrintManagementCapabilitiesSequence');
$this->dict[0x2130][0x0015] = array('SQ','1','PrinterCharacteristicsSequence');
$this->dict[0x2130][0x0030] = array('SQ','1','FilmBoxContentSequence');
$this->dict[0x2130][0x0040] = array('SQ','1','ImageBoxContentSequence');
$this->dict[0x2130][0x0050] = array('SQ','1','AnnotationContentSequence');
$this->dict[0x2130][0x0060] = array('SQ','1','ImageOverlayBoxContentSequence');
$this->dict[0x2130][0x0080] = array('SQ','1','PresentationLUTContentSequence');
$this->dict[0x2130][0x00A0] = array('SQ','1','ProposedStudySequence');
$this->dict[0x2130][0x00C0] = array('SQ','1','OriginalImageSequence');
$this->dict[0x3002][0x0000] = array('UL','1','RTImageGroupLength');
$this->dict[0x3002][0x0002] = array('SH','1','RTImageLabel');
$this->dict[0x3002][0x0003] = array('LO','1','RTImageName');
$this->dict[0x3002][0x0004] = array('ST','1','RTImageDescription');
$this->dict[0x3002][0x000A] = array('CS','1','ReportedValuesOrigin');
$this->dict[0x3002][0x000C] = array('CS','1','RTImagePlane');
$this->dict[0x3002][0x000D] = array('DS','3','XRayImageReceptorTranslation');
$this->dict[0x3002][0x000E] = array('DS','1','XRayImageReceptorAngle');
$this->dict[0x3002][0x0010] = array('DS','6','RTImageOrientation');
$this->dict[0x3002][0x0011] = array('DS','2','ImagePlanePixelSpacing');
$this->dict[0x3002][0x0012] = array('DS','2','RTImagePosition');
$this->dict[0x3002][0x0020] = array('SH','1','RadiationMachineName');
$this->dict[0x3002][0x0022] = array('DS','1','RadiationMachineSAD');
$this->dict[0x3002][0x0024] = array('DS','1','RadiationMachineSSD');
$this->dict[0x3002][0x0026] = array('DS','1','RTImageSID');
$this->dict[0x3002][0x0028] = array('DS','1','SourceToReferenceObjectDistance');
$this->dict[0x3002][0x0029] = array('IS','1','FractionNumber');
$this->dict[0x3002][0x0030] = array('SQ','1','ExposureSequence');
$this->dict[0x3002][0x0032] = array('DS','1','MetersetExposure');
$this->dict[0x3004][0x0000] = array('UL','1','RTDoseGroupLength');
$this->dict[0x3004][0x0001] = array('CS','1','DVHType');
$this->dict[0x3004][0x0002] = array('CS','1','DoseUnits');
$this->dict[0x3004][0x0004] = array('CS','1','DoseType');
$this->dict[0x3004][0x0006] = array('LO','1','DoseComment');
$this->dict[0x3004][0x0008] = array('DS','3','NormalizationPoint');
$this->dict[0x3004][0x000A] = array('CS','1','DoseSummationType');
$this->dict[0x3004][0x000C] = array('DS','2-n','GridFrameOffsetVector');
$this->dict[0x3004][0x000E] = array('DS','1','DoseGridScaling');
$this->dict[0x3004][0x0010] = array('SQ','1','RTDoseROISequence');
$this->dict[0x3004][0x0012] = array('DS','1','DoseValue');
$this->dict[0x3004][0x0040] = array('DS','3','DVHNormalizationPoint');
$this->dict[0x3004][0x0042] = array('DS','1','DVHNormalizationDoseValue');
$this->dict[0x3004][0x0050] = array('SQ','1','DVHSequence');
$this->dict[0x3004][0x0052] = array('DS','1','DVHDoseScaling');
$this->dict[0x3004][0x0054] = array('CS','1','DVHVolumeUnits');
$this->dict[0x3004][0x0056] = array('IS','1','DVHNumberOfBins');
$this->dict[0x3004][0x0058] = array('DS','2-2n','DVHData');
$this->dict[0x3004][0x0060] = array('SQ','1','DVHReferencedROISequence');
$this->dict[0x3004][0x0062] = array('CS','1','DVHROIContributionType');
$this->dict[0x3004][0x0070] = array('DS','1','DVHMinimumDose');
$this->dict[0x3004][0x0072] = array('DS','1','DVHMaximumDose');
$this->dict[0x3004][0x0074] = array('DS','1','DVHMeanDose');
$this->dict[0x3006][0x0000] = array('UL','1','RTStructureSetGroupLength');
$this->dict[0x3006][0x0002] = array('SH','1','StructureSetLabel');
$this->dict[0x3006][0x0004] = array('LO','1','StructureSetName');
$this->dict[0x3006][0x0006] = array('ST','1','StructureSetDescription');
$this->dict[0x3006][0x0008] = array('DA','1','StructureSetDate');
$this->dict[0x3006][0x0009] = array('TM','1','StructureSetTime');
$this->dict[0x3006][0x0010] = array('SQ','1','ReferencedFrameOfReferenceSequence');
$this->dict[0x3006][0x0012] = array('SQ','1','RTReferencedStudySequence');
$this->dict[0x3006][0x0014] = array('SQ','1','RTReferencedSeriesSequence');
$this->dict[0x3006][0x0016] = array('SQ','1','ContourImageSequence');
$this->dict[0x3006][0x0020] = array('SQ','1','StructureSetROISequence');
$this->dict[0x3006][0x0022] = array('IS','1','ROINumber');
$this->dict[0x3006][0x0024] = array('UI','1','ReferencedFrameOfReferenceUID');
$this->dict[0x3006][0x0026] = array('LO','1','ROIName');
$this->dict[0x3006][0x0028] = array('ST','1','ROIDescription');
$this->dict[0x3006][0x002A] = array('IS','3','ROIDisplayColor');
$this->dict[0x3006][0x002C] = array('DS','1','ROIVolume');
$this->dict[0x3006][0x0030] = array('SQ','1','RTRelatedROISequence');
$this->dict[0x3006][0x0033] = array('CS','1','RTROIRelationship');
$this->dict[0x3006][0x0036] = array('CS','1','ROIGenerationAlgorithm');
$this->dict[0x3006][0x0038] = array('LO','1','ROIGenerationDescription');
$this->dict[0x3006][0x0039] = array('SQ','1','ROIContourSequence');
$this->dict[0x3006][0x0040] = array('SQ','1','ContourSequence');
$this->dict[0x3006][0x0042] = array('CS','1','ContourGeometricType');
$this->dict[0x3006][0x0044] = array('DS','1','ContourSlabThickness');
$this->dict[0x3006][0x0045] = array('DS','3','ContourOffsetVector');
$this->dict[0x3006][0x0046] = array('IS','1','NumberOfContourPoints');
$this->dict[0x3006][0x0048] = array('IS','1','ContourNumber');
$this->dict[0x3006][0x0049] = array('IS','1-n','AttachedContours');
$this->dict[0x3006][0x0050] = array('DS','3-3n','ContourData');
$this->dict[0x3006][0x0080] = array('SQ','1','RTROIObservationsSequence');
$this->dict[0x3006][0x0082] = array('IS','1','ObservationNumber');
$this->dict[0x3006][0x0084] = array('IS','1','ReferencedROINumber');
$this->dict[0x3006][0x0085] = array('SH','1','ROIObservationLabel');
$this->dict[0x3006][0x0086] = array('SQ','1','RTROIIdentificationCodeSequence');
$this->dict[0x3006][0x0088] = array('ST','1','ROIObservationDescription');
$this->dict[0x3006][0x00A0] = array('SQ','1','RelatedRTROIObservationsSequence');
$this->dict[0x3006][0x00A4] = array('CS','1','RTROIInterpretedType');
$this->dict[0x3006][0x00A6] = array('PN','1','ROIInterpreter');
$this->dict[0x3006][0x00B0] = array('SQ','1','ROIPhysicalPropertiesSequence');
$this->dict[0x3006][0x00B2] = array('CS','1','ROIPhysicalProperty');
$this->dict[0x3006][0x00B4] = array('DS','1','ROIPhysicalPropertyValue');
$this->dict[0x3006][0x00C0] = array('SQ','1','FrameOfReferenceRelationshipSequence');
$this->dict[0x3006][0x00C2] = array('UI','1','RelatedFrameOfReferenceUID');
$this->dict[0x3006][0x00C4] = array('CS','1','FrameOfReferenceTransformationType');
$this->dict[0x3006][0x00C6] = array('DS','16','FrameOfReferenceTransformationMatrix');
$this->dict[0x3006][0x00C8] = array('LO','1','FrameOfReferenceTransformationComment');
$this->dict[0x3008][0x0010] = array('SQ','1','MeasuredDoseReferenceSequence');
$this->dict[0x3008][0x0012] = array('ST','1','MeasuredDoseDescription');
$this->dict[0x3008][0x0014] = array('CS','1','MeasuredDoseType');
$this->dict[0x3008][0x0016] = array('DS','1','MeasuredDoseValue');
$this->dict[0x3008][0x0020] = array('SQ','1','TreatmentSessionBeamSequence');
$this->dict[0x3008][0x0022] = array('IS','1','CurrentFractionNumber');
$this->dict[0x3008][0x0024] = array('DA','1','TreatmentControlPointDate');
$this->dict[0x3008][0x0025] = array('TM','1','TreatmentControlPointTime');
$this->dict[0x3008][0x002A] = array('CS','1','TreatmentTerminationStatus');
$this->dict[0x3008][0x002B] = array('SH','1','TreatmentTerminationCode');
$this->dict[0x3008][0x002C] = array('CS','1','TreatmentVerificationStatus');
$this->dict[0x3008][0x0030] = array('SQ','1','ReferencedTreatmentRecordSequence');
$this->dict[0x3008][0x0032] = array('DS','1','SpecifiedPrimaryMeterset');
$this->dict[0x3008][0x0033] = array('DS','1','SpecifiedSecondaryMeterset');
$this->dict[0x3008][0x0036] = array('DS','1','DeliveredPrimaryMeterset');
$this->dict[0x3008][0x0037] = array('DS','1','DeliveredSecondaryMeterset');
$this->dict[0x3008][0x003A] = array('DS','1','SpecifiedTreatmentTime');
$this->dict[0x3008][0x003B] = array('DS','1','DeliveredTreatmentTime');
$this->dict[0x3008][0x0040] = array('SQ','1','ControlPointDeliverySequence');
$this->dict[0x3008][0x0042] = array('DS','1','SpecifiedMeterset');
$this->dict[0x3008][0x0044] = array('DS','1','DeliveredMeterset');
$this->dict[0x3008][0x0048] = array('DS','1','DoseRateDelivered');
$this->dict[0x3008][0x0050] = array('SQ','1','TreatmentSummaryCalculatedDoseReferenceSequence');
$this->dict[0x3008][0x0052] = array('DS','1','CumulativeDosetoDoseReference');
$this->dict[0x3008][0x0054] = array('DA','1','FirstTreatmentDate');
$this->dict[0x3008][0x0056] = array('DA','1','MostRecentTreatmentDate');
$this->dict[0x3008][0x005A] = array('IS','1','NumberofFractionsDelivered');
$this->dict[0x3008][0x0060] = array('SQ','1','OverrideSequence');
$this->dict[0x3008][0x0062] = array('AT','1','OverrideParameterPointer');
$this->dict[0x3008][0x0064] = array('IS','1','MeasuredDoseReferenceNumber');
$this->dict[0x3008][0x0066] = array('ST','1','OverrideReason');
$this->dict[0x3008][0x0070] = array('SQ','1','CalculatedDoseReferenceSequence');
$this->dict[0x3008][0x0072] = array('IS','1','CalculatedDoseReferenceNumber');
$this->dict[0x3008][0x0074] = array('ST','1','CalculatedDoseReferenceDescription');
$this->dict[0x3008][0x0076] = array('DS','1','CalculatedDoseReferenceDoseValue');
$this->dict[0x3008][0x0078] = array('DS','1','StartMeterset');
$this->dict[0x3008][0x007A] = array('DS','1','EndMeterset');
$this->dict[0x3008][0x0080] = array('SQ','1','ReferencedMeasuredDoseReferenceSequence');
$this->dict[0x3008][0x0082] = array('IS','1','ReferencedMeasuredDoseReferenceNumber');
$this->dict[0x3008][0x0090] = array('SQ','1','ReferencedCalculatedDoseReferenceSequence');
$this->dict[0x3008][0x0092] = array('IS','1','ReferencedCalculatedDoseReferenceNumber');
$this->dict[0x3008][0x00A0] = array('SQ','1','BeamLimitingDeviceLeafPairsSequence');
$this->dict[0x3008][0x00B0] = array('SQ','1','RecordedWedgeSequence');
$this->dict[0x3008][0x00C0] = array('SQ','1','RecordedCompensatorSequence');
$this->dict[0x3008][0x00D0] = array('SQ','1','RecordedBlockSequence');
$this->dict[0x3008][0x00E0] = array('SQ','1','TreatmentSummaryMeasuredDoseReferenceSequence');
$this->dict[0x3008][0x0100] = array('SQ','1','RecordedSourceSequence');
$this->dict[0x3008][0x0105] = array('LO','1','SourceSerialNumber');
$this->dict[0x3008][0x0110] = array('SQ','1','TreatmentSessionApplicationSetupSequence');
$this->dict[0x3008][0x0116] = array('CS','1','ApplicationSetupCheck');
$this->dict[0x3008][0x0120] = array('SQ','1','RecordedBrachyAccessoryDeviceSequence');
$this->dict[0x3008][0x0122] = array('IS','1','ReferencedBrachyAccessoryDeviceNumber');
$this->dict[0x3008][0x0130] = array('SQ','1','RecordedChannelSequence');
$this->dict[0x3008][0x0132] = array('DS','1','SpecifiedChannelTotalTime');
$this->dict[0x3008][0x0134] = array('DS','1','DeliveredChannelTotalTime');
$this->dict[0x3008][0x0136] = array('IS','1','SpecifiedNumberofPulses');
$this->dict[0x3008][0x0138] = array('IS','1','DeliveredNumberofPulses');
$this->dict[0x3008][0x013A] = array('DS','1','SpecifiedPulseRepetitionInterval');
$this->dict[0x3008][0x013C] = array('DS','1','DeliveredPulseRepetitionInterval');
$this->dict[0x3008][0x0140] = array('SQ','1','RecordedSourceApplicatorSequence');
$this->dict[0x3008][0x0142] = array('IS','1','ReferencedSourceApplicatorNumber');
$this->dict[0x3008][0x0150] = array('SQ','1','RecordedChannelShieldSequence');
$this->dict[0x3008][0x0152] = array('IS','1','ReferencedChannelShieldNumber');
$this->dict[0x3008][0x0160] = array('SQ','1','BrachyControlPointDeliveredSequence');
$this->dict[0x3008][0x0162] = array('DA','1','SafePositionExitDate');
$this->dict[0x3008][0x0164] = array('TM','1','SafePositionExitTime');
$this->dict[0x3008][0x0166] = array('DA','1','SafePositionReturnDate');
$this->dict[0x3008][0x0168] = array('TM','1','SafePositionReturnTime');
$this->dict[0x3008][0x0200] = array('CS','1','CurrentTreatmentStatus');
$this->dict[0x3008][0x0202] = array('ST','1','TreatmentStatusComment');
$this->dict[0x3008][0x0220] = array('SQ','1','FractionGroupSummarySequence');
$this->dict[0x3008][0x0223] = array('IS','1','ReferencedFractionNumber');
$this->dict[0x3008][0x0224] = array('CS','1','FractionGroupType');
$this->dict[0x3008][0x0230] = array('CS','1','BeamStopperPosition');
$this->dict[0x3008][0x0240] = array('SQ','1','FractionStatusSummarySequence');
$this->dict[0x3008][0x0250] = array('DA','1','TreatmentDate');
$this->dict[0x3008][0x0251] = array('TM','1','TreatmentTime');
$this->dict[0x300A][0x0000] = array('UL','1','RTPlanGroupLength');
$this->dict[0x300A][0x0002] = array('SH','1','RTPlanLabel');
$this->dict[0x300A][0x0003] = array('LO','1','RTPlanName');
$this->dict[0x300A][0x0004] = array('ST','1','RTPlanDescription');
$this->dict[0x300A][0x0006] = array('DA','1','RTPlanDate');
$this->dict[0x300A][0x0007] = array('TM','1','RTPlanTime');
$this->dict[0x300A][0x0009] = array('LO','1-n','TreatmentProtocols');
$this->dict[0x300A][0x000A] = array('CS','1','TreatmentIntent');
$this->dict[0x300A][0x000B] = array('LO','1-n','TreatmentSites');
$this->dict[0x300A][0x000C] = array('CS','1','RTPlanGeometry');
$this->dict[0x300A][0x000E] = array('ST','1','PrescriptionDescription');
$this->dict[0x300A][0x0010] = array('SQ','1','DoseReferenceSequence');
$this->dict[0x300A][0x0012] = array('IS','1','DoseReferenceNumber');
$this->dict[0x300A][0x0014] = array('CS','1','DoseReferenceStructureType');
$this->dict[0x300A][0x0015] = array('CS','1','NominalBeamEnergyUnit');
$this->dict[0x300A][0x0016] = array('LO','1','DoseReferenceDescription');
$this->dict[0x300A][0x0018] = array('DS','3','DoseReferencePointCoordinates');
$this->dict[0x300A][0x001A] = array('DS','1','NominalPriorDose');
$this->dict[0x300A][0x0020] = array('CS','1','DoseReferenceType');
$this->dict[0x300A][0x0021] = array('DS','1','ConstraintWeight');
$this->dict[0x300A][0x0022] = array('DS','1','DeliveryWarningDose');
$this->dict[0x300A][0x0023] = array('DS','1','DeliveryMaximumDose');
$this->dict[0x300A][0x0025] = array('DS','1','TargetMinimumDose');
$this->dict[0x300A][0x0026] = array('DS','1','TargetPrescriptionDose');
$this->dict[0x300A][0x0027] = array('DS','1','TargetMaximumDose');
$this->dict[0x300A][0x0028] = array('DS','1','TargetUnderdoseVolumeFraction');
$this->dict[0x300A][0x002A] = array('DS','1','OrganAtRiskFullVolumeDose');
$this->dict[0x300A][0x002B] = array('DS','1','OrganAtRiskLimitDose');
$this->dict[0x300A][0x002C] = array('DS','1','OrganAtRiskMaximumDose');
$this->dict[0x300A][0x002D] = array('DS','1','OrganAtRiskOverdoseVolumeFraction');
$this->dict[0x300A][0x0040] = array('SQ','1','ToleranceTableSequence');
$this->dict[0x300A][0x0042] = array('IS','1','ToleranceTableNumber');
$this->dict[0x300A][0x0043] = array('SH','1','ToleranceTableLabel');
$this->dict[0x300A][0x0044] = array('DS','1','GantryAngleTolerance');
$this->dict[0x300A][0x0046] = array('DS','1','BeamLimitingDeviceAngleTolerance');
$this->dict[0x300A][0x0048] = array('SQ','1','BeamLimitingDeviceToleranceSequence');
$this->dict[0x300A][0x004A] = array('DS','1','BeamLimitingDevicePositionTolerance');
$this->dict[0x300A][0x004C] = array('DS','1','PatientSupportAngleTolerance');
$this->dict[0x300A][0x004E] = array('DS','1','TableTopEccentricAngleTolerance');
$this->dict[0x300A][0x0051] = array('DS','1','TableTopVerticalPositionTolerance');
$this->dict[0x300A][0x0052] = array('DS','1','TableTopLongitudinalPositionTolerance');
$this->dict[0x300A][0x0053] = array('DS','1','TableTopLateralPositionTolerance');
$this->dict[0x300A][0x0055] = array('CS','1','RTPlanRelationship');
$this->dict[0x300A][0x0070] = array('SQ','1','FractionGroupSequence');
$this->dict[0x300A][0x0071] = array('IS','1','FractionGroupNumber');
$this->dict[0x300A][0x0078] = array('IS','1','NumberOfFractionsPlanned');
$this->dict[0x300A][0x0079] = array('IS','1','NumberOfFractionsPerDay');
$this->dict[0x300A][0x007A] = array('IS','1','RepeatFractionCycleLength');
$this->dict[0x300A][0x007B] = array('LT','1','FractionPattern');
$this->dict[0x300A][0x0080] = array('IS','1','NumberOfBeams');
$this->dict[0x300A][0x0082] = array('DS','3','BeamDoseSpecificationPoint');
$this->dict[0x300A][0x0084] = array('DS','1','BeamDose');
$this->dict[0x300A][0x0086] = array('DS','1','BeamMeterset');
$this->dict[0x300A][0x00A0] = array('IS','1','NumberOfBrachyApplicationSetups');
$this->dict[0x300A][0x00A2] = array('DS','3','BrachyApplicationSetupDoseSpecificationPoint');
$this->dict[0x300A][0x00A4] = array('DS','1','BrachyApplicationSetupDose');
$this->dict[0x300A][0x00B0] = array('SQ','1','BeamSequence');
$this->dict[0x300A][0x00B2] = array('SH','1','TreatmentMachineName');
$this->dict[0x300A][0x00B3] = array('CS','1','PrimaryDosimeterUnit');
$this->dict[0x300A][0x00B4] = array('DS','1','SourceAxisDistance');
$this->dict[0x300A][0x00B6] = array('SQ','1','BeamLimitingDeviceSequence');
$this->dict[0x300A][0x00B8] = array('CS','1','RTBeamLimitingDeviceType');
$this->dict[0x300A][0x00BA] = array('DS','1','SourceToBeamLimitingDeviceDistance');
$this->dict[0x300A][0x00BC] = array('IS','1','NumberOfLeafJawPairs');
$this->dict[0x300A][0x00BE] = array('DS','3-n','LeafPositionBoundaries');
$this->dict[0x300A][0x00C0] = array('IS','1','BeamNumber');
$this->dict[0x300A][0x00C2] = array('LO','1','BeamName');
$this->dict[0x300A][0x00C3] = array('ST','1','BeamDescription');
$this->dict[0x300A][0x00C4] = array('CS','1','BeamType');
$this->dict[0x300A][0x00C6] = array('CS','1','RadiationType');
$this->dict[0x300A][0x00C8] = array('IS','1','ReferenceImageNumber');
$this->dict[0x300A][0x00CA] = array('SQ','1','PlannedVerificationImageSequence');
$this->dict[0x300A][0x00CC] = array('LO','1-n','ImagingDeviceSpecificAcquisitionParameters');
$this->dict[0x300A][0x00CE] = array('CS','1','TreatmentDeliveryType');
$this->dict[0x300A][0x00D0] = array('IS','1','NumberOfWedges');
$this->dict[0x300A][0x00D1] = array('SQ','1','WedgeSequence');
$this->dict[0x300A][0x00D2] = array('IS','1','WedgeNumber');
$this->dict[0x300A][0x00D3] = array('CS','1','WedgeType');
$this->dict[0x300A][0x00D4] = array('SH','1','WedgeID');
$this->dict[0x300A][0x00D5] = array('IS','1','WedgeAngle');
$this->dict[0x300A][0x00D6] = array('DS','1','WedgeFactor');
$this->dict[0x300A][0x00D8] = array('DS','1','WedgeOrientation');
$this->dict[0x300A][0x00DA] = array('DS','1','SourceToWedgeTrayDistance');
$this->dict[0x300A][0x00E0] = array('IS','1','NumberOfCompensators');
$this->dict[0x300A][0x00E1] = array('SH','1','MaterialID');
$this->dict[0x300A][0x00E2] = array('DS','1','TotalCompensatorTrayFactor');
$this->dict[0x300A][0x00E3] = array('SQ','1','CompensatorSequence');
$this->dict[0x300A][0x00E4] = array('IS','1','CompensatorNumber');
$this->dict[0x300A][0x00E5] = array('SH','1','CompensatorID');
$this->dict[0x300A][0x00E6] = array('DS','1','SourceToCompensatorTrayDistance');
$this->dict[0x300A][0x00E7] = array('IS','1','CompensatorRows');
$this->dict[0x300A][0x00E8] = array('IS','1','CompensatorColumns');
$this->dict[0x300A][0x00E9] = array('DS','2','CompensatorPixelSpacing');
$this->dict[0x300A][0x00EA] = array('DS','2','CompensatorPosition');
$this->dict[0x300A][0x00EB] = array('DS','1-n','CompensatorTransmissionData');
$this->dict[0x300A][0x00EC] = array('DS','1-n','CompensatorThicknessData');
$this->dict[0x300A][0x00ED] = array('IS','1','NumberOfBoli');
$this->dict[0x300A][0x00EE] = array('CS','1','CompensatorType');
$this->dict[0x300A][0x00F0] = array('IS','1','NumberOfBlocks');
$this->dict[0x300A][0x00F2] = array('DS','1','TotalBlockTrayFactor');
$this->dict[0x300A][0x00F4] = array('SQ','1','BlockSequence');
$this->dict[0x300A][0x00F5] = array('SH','1','BlockTrayID');
$this->dict[0x300A][0x00F6] = array('DS','1','SourceToBlockTrayDistance');
$this->dict[0x300A][0x00F8] = array('CS','1','BlockType');
$this->dict[0x300A][0x00FA] = array('CS','1','BlockDivergence');
$this->dict[0x300A][0x00FC] = array('IS','1','BlockNumber');
$this->dict[0x300A][0x00FE] = array('LO','1','BlockName');
$this->dict[0x300A][0x0100] = array('DS','1','BlockThickness');
$this->dict[0x300A][0x0102] = array('DS','1','BlockTransmission');
$this->dict[0x300A][0x0104] = array('IS','1','BlockNumberOfPoints');
$this->dict[0x300A][0x0106] = array('DS','2-2n','BlockData');
$this->dict[0x300A][0x0107] = array('SQ','1','ApplicatorSequence');
$this->dict[0x300A][0x0108] = array('SH','1','ApplicatorID');
$this->dict[0x300A][0x0109] = array('CS','1','ApplicatorType');
$this->dict[0x300A][0x010A] = array('LO','1','ApplicatorDescription');
$this->dict[0x300A][0x010C] = array('DS','1','CumulativeDoseReferenceCoefficient');
$this->dict[0x300A][0x010E] = array('DS','1','FinalCumulativeMetersetWeight');
$this->dict[0x300A][0x0110] = array('IS','1','NumberOfControlPoints');
$this->dict[0x300A][0x0111] = array('SQ','1','ControlPointSequence');
$this->dict[0x300A][0x0112] = array('IS','1','ControlPointIndex');
$this->dict[0x300A][0x0114] = array('DS','1','NominalBeamEnergy');
$this->dict[0x300A][0x0115] = array('DS','1','DoseRateSet');
$this->dict[0x300A][0x0116] = array('SQ','1','WedgePositionSequence');
$this->dict[0x300A][0x0118] = array('CS','1','WedgePosition');
$this->dict[0x300A][0x011A] = array('SQ','1','BeamLimitingDevicePositionSequence');
$this->dict[0x300A][0x011C] = array('DS','2-2n','LeafJawPositions');
$this->dict[0x300A][0x011E] = array('DS','1','GantryAngle');
$this->dict[0x300A][0x011F] = array('CS','1','GantryRotationDirection');
$this->dict[0x300A][0x0120] = array('DS','1','BeamLimitingDeviceAngle');
$this->dict[0x300A][0x0121] = array('CS','1','BeamLimitingDeviceRotationDirection');
$this->dict[0x300A][0x0122] = array('DS','1','PatientSupportAngle');
$this->dict[0x300A][0x0123] = array('CS','1','PatientSupportRotationDirection');
$this->dict[0x300A][0x0124] = array('DS','1','TableTopEccentricAxisDistance');
$this->dict[0x300A][0x0125] = array('DS','1','TableTopEccentricAngle');
$this->dict[0x300A][0x0126] = array('CS','1','TableTopEccentricRotationDirection');
$this->dict[0x300A][0x0128] = array('DS','1','TableTopVerticalPosition');
$this->dict[0x300A][0x0129] = array('DS','1','TableTopLongitudinalPosition');
$this->dict[0x300A][0x012A] = array('DS','1','TableTopLateralPosition');
$this->dict[0x300A][0x012C] = array('DS','3','IsocenterPosition');
$this->dict[0x300A][0x012E] = array('DS','3','SurfaceEntryPoint');
$this->dict[0x300A][0x0130] = array('DS','1','SourceToSurfaceDistance');
$this->dict[0x300A][0x0134] = array('DS','1','CumulativeMetersetWeight');
$this->dict[0x300A][0x0180] = array('SQ','1','PatientSetupSequence');
$this->dict[0x300A][0x0182] = array('IS','1','PatientSetupNumber');
$this->dict[0x300A][0x0184] = array('LO','1','PatientAdditionalPosition');
$this->dict[0x300A][0x0190] = array('SQ','1','FixationDeviceSequence');
$this->dict[0x300A][0x0192] = array('CS','1','FixationDeviceType');
$this->dict[0x300A][0x0194] = array('SH','1','FixationDeviceLabel');
$this->dict[0x300A][0x0196] = array('ST','1','FixationDeviceDescription');
$this->dict[0x300A][0x0198] = array('SH','1','FixationDevicePosition');
$this->dict[0x300A][0x01A0] = array('SQ','1','ShieldingDeviceSequence');
$this->dict[0x300A][0x01A2] = array('CS','1','ShieldingDeviceType');
$this->dict[0x300A][0x01A4] = array('SH','1','ShieldingDeviceLabel');
$this->dict[0x300A][0x01A6] = array('ST','1','ShieldingDeviceDescription');
$this->dict[0x300A][0x01A8] = array('SH','1','ShieldingDevicePosition');
$this->dict[0x300A][0x01B0] = array('CS','1','SetupTechnique');
$this->dict[0x300A][0x01B2] = array('ST','1','SetupTechniqueDescription');
$this->dict[0x300A][0x01B4] = array('SQ','1','SetupDeviceSequence');
$this->dict[0x300A][0x01B6] = array('CS','1','SetupDeviceType');
$this->dict[0x300A][0x01B8] = array('SH','1','SetupDeviceLabel');
$this->dict[0x300A][0x01BA] = array('ST','1','SetupDeviceDescription');
$this->dict[0x300A][0x01BC] = array('DS','1','SetupDeviceParameter');
$this->dict[0x300A][0x01D0] = array('ST','1','SetupReferenceDescription');
$this->dict[0x300A][0x01D2] = array('DS','1','TableTopVerticalSetupDisplacement');
$this->dict[0x300A][0x01D4] = array('DS','1','TableTopLongitudinalSetupDisplacement');
$this->dict[0x300A][0x01D6] = array('DS','1','TableTopLateralSetupDisplacement');
$this->dict[0x300A][0x0200] = array('CS','1','BrachyTreatmentTechnique');
$this->dict[0x300A][0x0202] = array('CS','1','BrachyTreatmentType');
$this->dict[0x300A][0x0206] = array('SQ','1','TreatmentMachineSequence');
$this->dict[0x300A][0x0210] = array('SQ','1','SourceSequence');
$this->dict[0x300A][0x0212] = array('IS','1','SourceNumber');
$this->dict[0x300A][0x0214] = array('CS','1','SourceType');
$this->dict[0x300A][0x0216] = array('LO','1','SourceManufacturer');
$this->dict[0x300A][0x0218] = array('DS','1','ActiveSourceDiameter');
$this->dict[0x300A][0x021A] = array('DS','1','ActiveSourceLength');
$this->dict[0x300A][0x0222] = array('DS','1','SourceEncapsulationNominalThickness');
$this->dict[0x300A][0x0224] = array('DS','1','SourceEncapsulationNominalTransmission');
$this->dict[0x300A][0x0226] = array('LO','1','SourceIsotopeName');
$this->dict[0x300A][0x0228] = array('DS','1','SourceIsotopeHalfLife');
$this->dict[0x300A][0x022A] = array('DS','1','ReferenceAirKermaRate');
$this->dict[0x300A][0x022C] = array('DA','1','AirKermaRateReferenceDate');
$this->dict[0x300A][0x022E] = array('TM','1','AirKermaRateReferenceTime');
$this->dict[0x300A][0x0230] = array('SQ','1','ApplicationSetupSequence');
$this->dict[0x300A][0x0232] = array('CS','1','ApplicationSetupType');
$this->dict[0x300A][0x0234] = array('IS','1','ApplicationSetupNumber');
$this->dict[0x300A][0x0236] = array('LO','1','ApplicationSetupName');
$this->dict[0x300A][0x0238] = array('LO','1','ApplicationSetupManufacturer');
$this->dict[0x300A][0x0240] = array('IS','1','TemplateNumber');
$this->dict[0x300A][0x0242] = array('SH','1','TemplateType');
$this->dict[0x300A][0x0244] = array('LO','1','TemplateName');
$this->dict[0x300A][0x0250] = array('DS','1','TotalReferenceAirKerma');
$this->dict[0x300A][0x0260] = array('SQ','1','BrachyAccessoryDeviceSequence');
$this->dict[0x300A][0x0262] = array('IS','1','BrachyAccessoryDeviceNumber');
$this->dict[0x300A][0x0263] = array('SH','1','BrachyAccessoryDeviceID');
$this->dict[0x300A][0x0264] = array('CS','1','BrachyAccessoryDeviceType');
$this->dict[0x300A][0x0266] = array('LO','1','BrachyAccessoryDeviceName');
$this->dict[0x300A][0x026A] = array('DS','1','BrachyAccessoryDeviceNominalThickness');
$this->dict[0x300A][0x026C] = array('DS','1','BrachyAccessoryDeviceNominalTransmission');
$this->dict[0x300A][0x0280] = array('SQ','1','ChannelSequence');
$this->dict[0x300A][0x0282] = array('IS','1','ChannelNumber');
$this->dict[0x300A][0x0284] = array('DS','1','ChannelLength');
$this->dict[0x300A][0x0286] = array('DS','1','ChannelTotalTime');
$this->dict[0x300A][0x0288] = array('CS','1','SourceMovementType');
$this->dict[0x300A][0x028A] = array('IS','1','NumberOfPulses');
$this->dict[0x300A][0x028C] = array('DS','1','PulseRepetitionInterval');
$this->dict[0x300A][0x0290] = array('IS','1','SourceApplicatorNumber');
$this->dict[0x300A][0x0291] = array('SH','1','SourceApplicatorID');
$this->dict[0x300A][0x0292] = array('CS','1','SourceApplicatorType');
$this->dict[0x300A][0x0294] = array('LO','1','SourceApplicatorName');
$this->dict[0x300A][0x0296] = array('DS','1','SourceApplicatorLength');
$this->dict[0x300A][0x0298] = array('LO','1','SourceApplicatorManufacturer');
$this->dict[0x300A][0x029C] = array('DS','1','SourceApplicatorWallNominalThickness');
$this->dict[0x300A][0x029E] = array('DS','1','SourceApplicatorWallNominalTransmission');
$this->dict[0x300A][0x02A0] = array('DS','1','SourceApplicatorStepSize');
$this->dict[0x300A][0x02A2] = array('IS','1','TransferTubeNumber');
$this->dict[0x300A][0x02A4] = array('DS','1','TransferTubeLength');
$this->dict[0x300A][0x02B0] = array('SQ','1','ChannelShieldSequence');
$this->dict[0x300A][0x02B2] = array('IS','1','ChannelShieldNumber');
$this->dict[0x300A][0x02B3] = array('SH','1','ChannelShieldID');
$this->dict[0x300A][0x02B4] = array('LO','1','ChannelShieldName');
$this->dict[0x300A][0x02B8] = array('DS','1','ChannelShieldNominalThickness');
$this->dict[0x300A][0x02BA] = array('DS','1','ChannelShieldNominalTransmission');
$this->dict[0x300A][0x02C8] = array('DS','1','FinalCumulativeTimeWeight');
$this->dict[0x300A][0x02D0] = array('SQ','1','BrachyControlPointSequence');
$this->dict[0x300A][0x02D2] = array('DS','1','ControlPointRelativePosition');
$this->dict[0x300A][0x02D4] = array('DS','3','ControlPointDPosition');
$this->dict[0x300A][0x02D6] = array('DS','1','CumulativeTimeWeight');
$this->dict[0x300C][0x0000] = array('UL','1','RTRelationshipGroupLength');
$this->dict[0x300C][0x0002] = array('SQ','1','ReferencedRTPlanSequence');
$this->dict[0x300C][0x0004] = array('SQ','1','ReferencedBeamSequence');
$this->dict[0x300C][0x0006] = array('IS','1','ReferencedBeamNumber');
$this->dict[0x300C][0x0007] = array('IS','1','ReferencedReferenceImageNumber');
$this->dict[0x300C][0x0008] = array('DS','1','StartCumulativeMetersetWeight');
$this->dict[0x300C][0x0009] = array('DS','1','EndCumulativeMetersetWeight');
$this->dict[0x300C][0x000A] = array('SQ','1','ReferencedBrachyApplicationSetupSequence');
$this->dict[0x300C][0x000C] = array('IS','1','ReferencedBrachyApplicationSetupNumber');
$this->dict[0x300C][0x000E] = array('IS','1','ReferencedSourceNumber');
$this->dict[0x300C][0x0020] = array('SQ','1','ReferencedFractionGroupSequence');
$this->dict[0x300C][0x0022] = array('IS','1','ReferencedFractionGroupNumber');
$this->dict[0x300C][0x0040] = array('SQ','1','ReferencedVerificationImageSequence');
$this->dict[0x300C][0x0042] = array('SQ','1','ReferencedReferenceImageSequence');
$this->dict[0x300C][0x0050] = array('SQ','1','ReferencedDoseReferenceSequence');
$this->dict[0x300C][0x0051] = array('IS','1','ReferencedDoseReferenceNumber');
$this->dict[0x300C][0x0055] = array('SQ','1','BrachyReferencedDoseReferenceSequence');
$this->dict[0x300C][0x0060] = array('SQ','1','ReferencedStructureSetSequence');
$this->dict[0x300C][0x006A] = array('IS','1','ReferencedPatientSetupNumber');
$this->dict[0x300C][0x0080] = array('SQ','1','ReferencedDoseSequence');
$this->dict[0x300C][0x00A0] = array('IS','1','ReferencedToleranceTableNumber');
$this->dict[0x300C][0x00B0] = array('SQ','1','ReferencedBolusSequence');
$this->dict[0x300C][0x00C0] = array('IS','1','ReferencedWedgeNumber');
$this->dict[0x300C][0x00D0] = array('IS','1','ReferencedCompensatorNumber');
$this->dict[0x300C][0x00E0] = array('IS','1','ReferencedBlockNumber');
$this->dict[0x300C][0x00F0] = array('IS','1','ReferencedControlPointIndex');
$this->dict[0x300E][0x0000] = array('UL','1','RTApprovalGroupLength');
$this->dict[0x300E][0x0002] = array('CS','1','ApprovalStatus');
$this->dict[0x300E][0x0004] = array('DA','1','ReviewDate');
$this->dict[0x300E][0x0005] = array('TM','1','ReviewTime');
$this->dict[0x300E][0x0008] = array('PN','1','ReviewerName');
$this->dict[0x4000][0x0000] = array('UL','1','TextGroupLength');
$this->dict[0x4000][0x0010] = array('LT','1-n','TextArbitrary');
$this->dict[0x4000][0x4000] = array('LT','1-n','TextComments');
$this->dict[0x4008][0x0000] = array('UL','1','ResultsGroupLength');
$this->dict[0x4008][0x0040] = array('SH','1','ResultsID');
$this->dict[0x4008][0x0042] = array('LO','1','ResultsIDIssuer');
$this->dict[0x4008][0x0050] = array('SQ','1','ReferencedInterpretationSequence');
$this->dict[0x4008][0x0100] = array('DA','1','InterpretationRecordedDate');
$this->dict[0x4008][0x0101] = array('TM','1','InterpretationRecordedTime');
$this->dict[0x4008][0x0102] = array('PN','1','InterpretationRecorder');
$this->dict[0x4008][0x0103] = array('LO','1','ReferenceToRecordedSound');
$this->dict[0x4008][0x0108] = array('DA','1','InterpretationTranscriptionDate');
$this->dict[0x4008][0x0109] = array('TM','1','InterpretationTranscriptionTime');
$this->dict[0x4008][0x010A] = array('PN','1','InterpretationTranscriber');
$this->dict[0x4008][0x010B] = array('ST','1','InterpretationText');
$this->dict[0x4008][0x010C] = array('PN','1','InterpretationAuthor');
$this->dict[0x4008][0x0111] = array('SQ','1','InterpretationApproverSequence');
$this->dict[0x4008][0x0112] = array('DA','1','InterpretationApprovalDate');
$this->dict[0x4008][0x0113] = array('TM','1','InterpretationApprovalTime');
$this->dict[0x4008][0x0114] = array('PN','1','PhysicianApprovingInterpretation');
$this->dict[0x4008][0x0115] = array('LT','1','InterpretationDiagnosisDescription');
$this->dict[0x4008][0x0117] = array('SQ','1','DiagnosisCodeSequence');
$this->dict[0x4008][0x0118] = array('SQ','1','ResultsDistributionListSequence');
$this->dict[0x4008][0x0119] = array('PN','1','DistributionName');
$this->dict[0x4008][0x011A] = array('LO','1','DistributionAddress');
$this->dict[0x4008][0x0200] = array('SH','1','InterpretationID');
$this->dict[0x4008][0x0202] = array('LO','1','InterpretationIDIssuer');
$this->dict[0x4008][0x0210] = array('CS','1','InterpretationTypeID');
$this->dict[0x4008][0x0212] = array('CS','1','InterpretationStatusID');
$this->dict[0x4008][0x0300] = array('ST','1','Impressions');
$this->dict[0x4008][0x4000] = array('ST','1','ResultsComments');
$this->dict[0x5000][0x0000] = array('UL','1','CurveGroupLength');
$this->dict[0x5000][0x0005] = array('US','1','CurveDimensions');
$this->dict[0x5000][0x0010] = array('US','1','NumberOfPoints');
$this->dict[0x5000][0x0020] = array('CS','1','TypeOfData');
$this->dict[0x5000][0x0022] = array('LO','1','CurveDescription');
$this->dict[0x5000][0x0030] = array('SH','1-n','AxisUnits');
$this->dict[0x5000][0x0040] = array('SH','1-n','AxisLabels');
$this->dict[0x5000][0x0103] = array('US','1','DataValueRepresentation');
$this->dict[0x5000][0x0104] = array('US','1-n','MinimumCoordinateValue');
$this->dict[0x5000][0x0105] = array('US','1-n','MaximumCoordinateValue');
$this->dict[0x5000][0x0106] = array('SH','1-n','CurveRange');
$this->dict[0x5000][0x0110] = array('US','1','CurveDataDescriptor');
$this->dict[0x5000][0x0112] = array('US','1','CoordinateStartValue');
$this->dict[0x5000][0x0114] = array('US','1','CoordinateStepValue');
$this->dict[0x5000][0x2000] = array('US','1','AudioType');
$this->dict[0x5000][0x2002] = array('US','1','AudioSampleFormat');
$this->dict[0x5000][0x2004] = array('US','1','NumberOfChannels');
$this->dict[0x5000][0x2006] = array('UL','1','NumberOfSamples');
$this->dict[0x5000][0x2008] = array('UL','1','SampleRate');
$this->dict[0x5000][0x200A] = array('UL','1','TotalTime');
$this->dict[0x5000][0x200C] = array('OX','1','AudioSampleData');
$this->dict[0x5000][0x200E] = array('LT','1','AudioComments');
$this->dict[0x5000][0x3000] = array('OX','1','CurveData');
$this->dict[0x5400][0x0100] = array('SQ','1','WaveformSequence');
$this->dict[0x5400][0x0110] = array('OW/OB','1','ChannelMinimumValue');
$this->dict[0x5400][0x0112] = array('OW/OB','1','ChannelMaximumValue');
$this->dict[0x5400][0x1004] = array('US','1','WaveformBitsAllocated');
$this->dict[0x5400][0x1006] = array('CS','1','WaveformSampleInterpretation');
$this->dict[0x5400][0x100A] = array('OW/OB','1','WaveformPaddingValue');
$this->dict[0x5400][0x1010] = array('OW/OB','1','WaveformData');
$this->dict[0x6000][0x0000] = array('UL','1','OverlayGroupLength');
$this->dict[0x6000][0x0010] = array('US','1','OverlayRows');
$this->dict[0x6000][0x0011] = array('US','1','OverlayColumns');
$this->dict[0x6000][0x0012] = array('US','1','OverlayPlanes');
$this->dict[0x6000][0x0015] = array('IS','1','OverlayNumberOfFrames');
$this->dict[0x6000][0x0040] = array('CS','1','OverlayType');
$this->dict[0x6000][0x0050] = array('SS','2','OverlayOrigin');
$this->dict[0x6000][0x0051] = array('US','1','OverlayImageFrameOrigin');
$this->dict[0x6000][0x0052] = array('US','1','OverlayPlaneOrigin');
$this->dict[0x6000][0x0060] = array('CS','1','OverlayCompressionCode');
$this->dict[0x6000][0x0061] = array('SH','1','OverlayCompressionOriginator');
$this->dict[0x6000][0x0062] = array('SH','1','OverlayCompressionLabel');
$this->dict[0x6000][0x0063] = array('SH','1','OverlayCompressionDescription');
$this->dict[0x6000][0x0066] = array('AT','1-n','OverlayCompressionStepPointers');
$this->dict[0x6000][0x0068] = array('US','1','OverlayRepeatInterval');
$this->dict[0x6000][0x0069] = array('US','1','OverlayBitsGrouped');
$this->dict[0x6000][0x0100] = array('US','1','OverlayBitsAllocated');
$this->dict[0x6000][0x0102] = array('US','1','OverlayBitPosition');
$this->dict[0x6000][0x0110] = array('CS','1','OverlayFormat');
$this->dict[0x6000][0x0200] = array('US','1','OverlayLocation');
$this->dict[0x6000][0x0800] = array('CS','1-n','OverlayCodeLabel');
$this->dict[0x6000][0x0802] = array('US','1','OverlayNumberOfTables');
$this->dict[0x6000][0x0803] = array('AT','1-n','OverlayCodeTableLocation');
$this->dict[0x6000][0x0804] = array('US','1','OverlayBitsForCodeWord');
$this->dict[0x6000][0x1100] = array('US','1','OverlayDescriptorGray');
$this->dict[0x6000][0x1101] = array('US','1','OverlayDescriptorRed');
$this->dict[0x6000][0x1102] = array('US','1','OverlayDescriptorGreen');
$this->dict[0x6000][0x1103] = array('US','1','OverlayDescriptorBlue');
$this->dict[0x6000][0x1200] = array('US','1-n','OverlayGray');
$this->dict[0x6000][0x1201] = array('US','1-n','OverlayRed');
$this->dict[0x6000][0x1202] = array('US','1-n','OverlayGreen');
$this->dict[0x6000][0x1203] = array('US','1-n','OverlayBlue');
$this->dict[0x6000][0x1301] = array('IS','1','ROIArea');
$this->dict[0x6000][0x1302] = array('DS','1','ROIMean');
$this->dict[0x6000][0x1303] = array('DS','1','ROIStandardDeviation');
$this->dict[0x6000][0x3000] = array('OW','1','OverlayData');
$this->dict[0x6000][0x4000] = array('LT','1-n','OverlayComments');
$this->dict[0x7F00][0x0000] = array('UL','1','VariablePixelDataGroupLength');
$this->dict[0x7F00][0x0010] = array('OX','1','VariablePixelData');
$this->dict[0x7F00][0x0011] = array('AT','1','VariableNextDataGroup');
$this->dict[0x7F00][0x0020] = array('OW','1-n','VariableCoefficientsSDVN');
$this->dict[0x7F00][0x0030] = array('OW','1-n','VariableCoefficientsSDHN');
$this->dict[0x7F00][0x0040] = array('OW','1-n','VariableCoefficientsSDDN');
$this->dict[0x7FE0][0x0000] = array('UL','1','PixelDataGroupLength');
$this->dict[0x7FE0][0x0010] = array('OX','1','PixelData');
$this->dict[0x7FE0][0x0020] = array('OW','1-n','CoefficientsSDVN');
$this->dict[0x7FE0][0x0030] = array('OW','1-n','CoefficientsSDHN');
$this->dict[0x7FE0][0x0040] = array('OW','1-n','CoefficientsSDDN');
$this->dict[0xFFFC][0xFFFC] = array('OB','1','DataSetTrailingPadding');
$this->dict[0xFFFE][0xE000] = array('NONE','1','Item');
$this->dict[0xFFFE][0xE00D] = array('NONE','1','ItemDelimitationItem');
$this->dict[0xFFFE][0xE0DD] = array('NONE','1','SequenceDelimitationItem');
    }

    /**
    * Parse a DICOM file
    * Parse a DICOM file and get all of its header members
    *
    * @access public
    * @param string $infile The DICOM file to parse
    * @return mixed true on success, PEAR_Error on failure
    */
    function parse($infile)
    {
        $this->current_file = $infile;
        $fh = @fopen($infile, "rb");
        if (!$fh) {
            return $this->raiseError("Could not open file $infile for reading");
        }
        $stat = fstat($fh);
        $this->_file_length = $stat[7];
  
        // Test for NEMA or DICOM file.  
        // If DICM, store initial preamble and leave file ptr at 0x84.
        $this->_preamble_buff = fread($fh, 0x80);
        $buff = fread($fh, 4);
        $this->is_dicm = ($buff == 'DICM');
        if (!$this->is_dicm) {
            fseek($fh, 0);
        }
  
        // Fill in hash with header members from given file.
        while (ftell($fh) < $this->_file_length)
        {
            $element =& new File_DICOM_Element($fh, $this->dict);
            $this->_elements[$element->group][$element->element] =& $element;
            $this->_elements_by_name[$element->name] =& $element;
        }
        fclose($fh);
        return true;
    }

    /**
    * Write current contents to a DICOM file.
    *
    * @access public
    * @param string $outfile The name of the file to write. If not given 
    *                        it assumes the name of the file parsed.
    *                        If no file was parsed and no name is given
    *                        returns a PEAR_Error
    * @return mixed true on success, PEAR_Error on failure
    */
    function write($outfile = '')
    {
        if ($outfile == '') {
            if (isset($this->current_file)) {
                $outfile = $this->current_file;
            } else {
                return $this->raiseError("File name not given (and no file currently open)");
            }
        }
        $fh = @fopen($outfile, "wb");
        if (!$fh) {
            return $this->raiseError("Could not open file $outfile for writing");
        }
  
        // Writing file from scratch will always fail for now
        if (!isset($this->_preamble_buff)) {
            return $this->raiseError("Cannot write DICOM file from scratch");
        }
        // Don't store initial preamble and DICM word. Working with NEMA.
        //fwrite($fh, $this->_preamble_buff);
        //fwrite($fh, 'DICM');
        /*$buff = fread($fh, 4);
        $this->is_dicm = ($buff == 'DICM');
        if (!$this->is_dicm) {
            fseek($fh, 0);
        }*/
 
        // There are not that much groups/elements. Using foreach()
        foreach (array_keys($this->_elements) as $group) {
            foreach (array_keys($this->_elements[$group]) as $element) {
                fwrite($fh, pack('v', $group));
                fwrite($fh, pack('v', $element));
                $code = $this->_elements[$group][$element]->code;
                // Preserve the VR type from the file parsed
                if (($this->_elements[$group][$element]->vr_type == FILE_DICOM_VR_TYPE_EXPLICIT_32_BITS) or ($this->_elements[$group][$element]->vr_type == FILE_DICOM_VR_TYPE_EXPLICIT_16_BITS)) {
                    fwrite($fh, pack('CC', $code{0}, $code{1}));
                    // This is an OB, OW, SQ, UN or UT: 32 bit VL field.
                    if ($this->_elements[$group][$element]->vr_type == FILE_DICOM_VR_TYPE_EXPLICIT_32_BITS) {
                        fwrite($fh, pack('V', $this->_elements[$group][$element]->length));
                    } else { // not using fixed length from VR!!!
                        fwrite($fh, pack('v', $this->_elements[$group][$element]->length));
                    }
                } elseif ($this->_elements[$group][$element]->vr_type == FILE_DICOM_VR_TYPE_IMPLICIT) {
                    fwrite($fh, pack('V', $this->_elements[$group][$element]->length));
                }
                switch ($code) {
                    // Decode unsigned longs and shorts.
                    case 'UL':
                        fwrite($fh, pack('V', $this->_elements[$group][$element]->value));
                        break;
                    case 'US':
                        fwrite($fh, pack('v', $this->_elements[$group][$element]->value));
                        break;
                    // Floats: Single and double precision.
                    case 'FL':
                        fwrite($fh, pack('f', $this->_elements[$group][$element]->value));
                        break;
                    case 'FD':
                        fwrite($fh, pack('d', $this->_elements[$group][$element]->value));
                        break;
                    // Binary data. Only save position. Is this right? 
                    case 'OW':
                    case 'OB':
                    case 'OX':
                        // Binary data. Value only contains position on the parsed file.
                        // Will fail when file name for writing is the same as for parsing.
                        $fh2 = @fopen($this->current_file, "rb");
                        if (!$fh2) {
                            return $this->raiseError("Could not open file {$this->current_file} for reading");
                        }
                        fseek($fh2, $this->_elements[$group][$element]->value);
                        fwrite($fh, fread($fh2, $this->_elements[$group][$element]->length));
                        fclose($fh2);
                        break;
                    default:
                        fwrite($fh, $this->_elements[$group][$element]->value, $this->_elements[$group][$element]->length);
                        break;
                }
            }
        }
        fclose($fh);
        return true;
    }

    /**
    * Gets the value for a DICOM element
    * Gets the value for a DICOM element of a given group from the
    * parsed DICOM file.
    *
    * @access public
    * @param mixed $gp_or_name The group the DICOM element belongs to 
    *                          (integer), or it's name (string)
    * @param integer $el       The identifier for the DICOM element
    *                          (unique inside a group)
    * @return mixed The value for the DICOM element on success, PEAR_Error on failure 
    */
    function getValue($gp_or_name, $el = null)
    {
        if (isset($el)) // retreive by group and element index
        {
            if (isset($this->_elements[$gp_or_name][$el])) {
                return $this->_elements[$gp_or_name][$el]->getValue();
            }
            else {
                return $this->raiseError("Element ($gp_or_name,$el) not found");
            }
        }
        else // retreive by name
        {
            if (isset($this->_elements_by_name[$gp_or_name])) {
                return $this->_elements_by_name[$gp_or_name]->getValue();
            }
            else {
                return $this->raiseError("Element $gp_or_name not found");
            }
        }
    }

    /**
    * Sets the value for a DICOM element
    * Only works with strings now.
    *
    * @access public
    * @param integer $gp The group the DICOM element belongs to
    * @param integer $el The identifier for the DICOM element (unique inside a group)
    */
    function setValue($gp, $el, $value)
    {
        $this->_elements[$gp][$el]->value = $value;
        $this->_elements[$gp][$el]->length = strlen($value);
    }

    /**
    * Dumps the contents of the image inside the DICOM file 
    * (element 0x0010 from group 0x7FE0) to a PGM (Portable Gray Map) file.
    * Use with Caution!!. For a 8.5MB DICOM file on a P4 it takes 28 
    * seconds to dump it's image.
    *
    * @access public
    * @param string  $filename The file where to save the image
    * @return mixed true on success, PEAR_Error on failure.
    */
    function dumpImage($filename)
    {
        $length = $this->_elements[0x7FE0][0x0010]->length;
        $rows = $this->getValue(0x0028,0x0010);
        $cols = $this->getValue(0x0028,0x0011);
        $fh = @fopen($filename, "wb");
        if (!$fh) {
            return $this->raiseError("Could not open file $filename for writing");
        }
        // magick word
        fwrite($fh, "P5\n");
        // comment
        fwrite($fh, "# file generated by PEAR::File_DICOM on ".strftime("%Y-%m-%d", time())." \n");
        fwrite($fh, "# do not use for diagnosing purposes\n");
        fwrite($fh, "$cols $rows\n");
        // always 255 grays
        fwrite($fh, "255\n");
        $pos = $this->getValue(0x7FE0,0x0010);
        $fh_in = @fopen($this->current_file, "rb");
        if (!$fh_in) {
            return $this->raiseError("Could not open file {$this->current_file} for reading");
        }
        fseek($fh_in, $pos);
        $block_size = 4096;
        $blocks = ceil($length / $block_size);
        for ($i = 0; $i < $blocks; $i++) {
            if ($i == $blocks - 1) { // last block
                $chunk_length = ($length % $block_size) ? ($length % $block_size) : $block_size;
            } else {
                $chunk_length = $block_size;
            }
            $chunk = fread($fh_in, $chunk_length);
            $pgm = '';
            $half_chunk_length = $chunk_length/2;
            $rr = unpack("v$half_chunk_length", $chunk);
            for ($j = 1; $j <= $half_chunk_length; $j++) {
                $pgm .= pack('C', $rr[$j] >> 4);
            }
            fwrite($fh, $pgm);
        }
        fclose($fh_in);
        fclose($fh);
        return true;
    }
}
?>
