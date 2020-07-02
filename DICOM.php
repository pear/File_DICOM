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
require_once('DICOM/Element.php');


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
    protected $dict;

    /**
    * Flag indicating if the current file is a DICM file or a NEMA file.
    * true => DICM, false => NEMA.
    *
    * @var bool
    */
    protected $isDicom;

    /**
    * Currently open file.
    * @var string
    */
    protected $currentFile;

    /**
    * Initial 0x80 bytes of last read file
    * @var string
    */
    protected $preambleBuff;

    /**
    * Array of DICOM elements indexed by group and element index
    * @var array
    */
    protected $elements;

    /**
    * Array of DICOM elements indexed by name
    * @var array
    */
    protected $elementsByName;

    /**
    * Constructor.
    * It creates a File_DICOM object.
    */
    public function __construct()
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
        $this->dict[0x0000][0x0000] = ['UL','1','GroupLength'];
        $this->dict[0x0000][0x0001] = ['UL','1','CommandLengthToEnd'];
        $this->dict[0x0000][0x0002] = ['UI','1','AffectedSOPClassUID'];
        $this->dict[0x0000][0x0003] = ['UI','1','RequestedSOPClassUID'];
        $this->dict[0x0000][0x0010] = ['CS','1','CommandRecognitionCode'];
        $this->dict[0x0000][0x0100] = ['US','1','CommandField'];
        $this->dict[0x0000][0x0110] = ['US','1','MessageID'];
        $this->dict[0x0000][0x0120] = ['US','1','MessageIDBeingRespondedTo'];
        $this->dict[0x0000][0x0200] = ['AE','1','Initiator'];
        $this->dict[0x0000][0x0300] = ['AE','1','Receiver'];
        $this->dict[0x0000][0x0400] = ['AE','1','FindLocation'];
        $this->dict[0x0000][0x0600] = ['AE','1','MoveDestination'];
        $this->dict[0x0000][0x0700] = ['US','1','Priority'];
        $this->dict[0x0000][0x0800] = ['US','1','DataSetType'];
        $this->dict[0x0000][0x0850] = ['US','1','NumberOfMatches'];
        $this->dict[0x0000][0x0860] = ['US','1','ResponseSequenceNumber'];
        $this->dict[0x0000][0x0900] = ['US','1','Status'];
        $this->dict[0x0000][0x0901] = ['AT','1-n','OffendingElement'];
        $this->dict[0x0000][0x0902] = ['LO','1','ErrorComment'];
        $this->dict[0x0000][0x0903] = ['US','1','ErrorID'];
        $this->dict[0x0000][0x0904] = ['OT','1-n','ErrorInformation'];
        $this->dict[0x0000][0x1000] = ['UI','1','AffectedSOPInstanceUID'];
        $this->dict[0x0000][0x1001] = ['UI','1','RequestedSOPInstanceUID'];
        $this->dict[0x0000][0x1002] = ['US','1','EventTypeID'];
        $this->dict[0x0000][0x1003] = ['OT','1-n','EventInformation'];
        $this->dict[0x0000][0x1005] = ['AT','1-n','AttributeIdentifierList'];
        $this->dict[0x0000][0x1007] = ['AT','1-n','ModificationList'];
        $this->dict[0x0000][0x1008] = ['US','1','ActionTypeID'];
        $this->dict[0x0000][0x1009] = ['OT','1-n','ActionInformation'];
        $this->dict[0x0000][0x1013] = ['UI','1-n','SuccessfulSOPInstanceUIDList'];
        $this->dict[0x0000][0x1014] = ['UI','1-n','FailedSOPInstanceUIDList'];
        $this->dict[0x0000][0x1015] = ['UI','1-n','WarningSOPInstanceUIDList'];
        $this->dict[0x0000][0x1020] = ['US','1','NumberOfRemainingSuboperations'];
        $this->dict[0x0000][0x1021] = ['US','1','NumberOfCompletedSuboperations'];
        $this->dict[0x0000][0x1022] = ['US','1','NumberOfFailedSuboperations'];
        $this->dict[0x0000][0x1023] = ['US','1','NumberOfWarningSuboperations'];
        $this->dict[0x0000][0x1030] = ['AE','1','MoveOriginatorApplicationEntityTitle'];
        $this->dict[0x0000][0x1031] = ['US','1','MoveOriginatorMessageID'];
        $this->dict[0x0000][0x4000] = ['AT','1','DialogReceiver'];
        $this->dict[0x0000][0x4010] = ['AT','1','TerminalType'];
        $this->dict[0x0000][0x5010] = ['SH','1','MessageSetID'];
        $this->dict[0x0000][0x5020] = ['SH','1','EndMessageSet'];
        $this->dict[0x0000][0x5110] = ['AT','1','DisplayFormat'];
        $this->dict[0x0000][0x5120] = ['AT','1','PagePositionID'];
        $this->dict[0x0000][0x5130] = ['CS','1','TextFormatID'];
        $this->dict[0x0000][0x5140] = ['CS','1','NormalReverse'];
        $this->dict[0x0000][0x5150] = ['CS','1','AddGrayScale'];
        $this->dict[0x0000][0x5160] = ['CS','1','Borders'];
        $this->dict[0x0000][0x5170] = ['IS','1','Copies'];
        $this->dict[0x0000][0x5180] = ['CS','1','OldMagnificationType'];
        $this->dict[0x0000][0x5190] = ['CS','1','Erase'];
        $this->dict[0x0000][0x51A0] = ['CS','1','Print'];
        $this->dict[0x0000][0x51B0] = ['US','1-n','Overlays'];
        $this->dict[0x0002][0x0000] = ['UL','1','MetaElementGroupLength'];
        $this->dict[0x0002][0x0001] = ['OB','1','FileMetaInformationVersion'];
        $this->dict[0x0002][0x0002] = ['UI','1','MediaStorageSOPClassUID'];
        $this->dict[0x0002][0x0003] = ['UI','1','MediaStorageSOPInstanceUID'];
        $this->dict[0x0002][0x0010] = ['UI','1','TransferSyntaxUID'];
        $this->dict[0x0002][0x0012] = ['UI','1','ImplementationClassUID'];
        $this->dict[0x0002][0x0013] = ['SH','1','ImplementationVersionName'];
        $this->dict[0x0002][0x0016] = ['AE','1','SourceApplicationEntityTitle'];
        $this->dict[0x0002][0x0100] = ['UI','1','PrivateInformationCreatorUID'];
        $this->dict[0x0002][0x0102] = ['OB','1','PrivateInformation'];
        $this->dict[0x0004][0x0000] = ['UL','1','FileSetGroupLength'];
        $this->dict[0x0004][0x1130] = ['CS','1','FileSetID'];
        $this->dict[0x0004][0x1141] = ['CS','8','FileSetDescriptorFileID'];
        $this->dict[0x0004][0x1142] = ['CS','1','FileSetCharacterSet'];
        $this->dict[0x0004][0x1200] = ['UL','1','RootDirectoryFirstRecord'];
        $this->dict[0x0004][0x1202] = ['UL','1','RootDirectoryLastRecord'];
        $this->dict[0x0004][0x1212] = ['US','1','FileSetConsistencyFlag'];
        $this->dict[0x0004][0x1220] = ['SQ','1','DirectoryRecordSequence'];
        $this->dict[0x0004][0x1400] = ['UL','1','NextDirectoryRecordOffset'];
        $this->dict[0x0004][0x1410] = ['US','1','RecordInUseFlag'];
        $this->dict[0x0004][0x1420] = ['UL','1','LowerLevelDirectoryOffset'];
        $this->dict[0x0004][0x1430] = ['CS','1','DirectoryRecordType'];
        $this->dict[0x0004][0x1432] = ['UI','1','PrivateRecordUID'];
        $this->dict[0x0004][0x1500] = ['CS','8','ReferencedFileID'];
        $this->dict[0x0004][0x1504] = ['UL','1','DirectoryRecordOffset'];
        $this->dict[0x0004][0x1510] = ['UI','1','ReferencedSOPClassUIDInFile'];
        $this->dict[0x0004][0x1511] = ['UI','1','ReferencedSOPInstanceUIDInFile'];
        $this->dict[0x0004][0x1512] = ['UI','1','ReferencedTransferSyntaxUIDInFile'];
        $this->dict[0x0004][0x1600] = ['UL','1','NumberOfReferences'];
        $this->dict[0x0008][0x0000] = ['UL','1','IdentifyingGroupLength'];
        $this->dict[0x0008][0x0001] = ['UL','1','LengthToEnd'];
        $this->dict[0x0008][0x0005] = ['CS','1','SpecificCharacterSet'];
        $this->dict[0x0008][0x0008] = ['CS','1-n','ImageType'];
        $this->dict[0x0008][0x000A] = ['US','1','SequenceItemNumber'];
        $this->dict[0x0008][0x0010] = ['CS','1','RecognitionCode'];
        $this->dict[0x0008][0x0012] = ['DA','1','InstanceCreationDate'];
        $this->dict[0x0008][0x0013] = ['TM','1','InstanceCreationTime'];
        $this->dict[0x0008][0x0014] = ['UI','1','InstanceCreatorUID'];
        $this->dict[0x0008][0x0016] = ['UI','1','SOPClassUID'];
        $this->dict[0x0008][0x0018] = ['UI','1','SOPInstanceUID'];
        $this->dict[0x0008][0x0020] = ['DA','1','StudyDate'];
        $this->dict[0x0008][0x0021] = ['DA','1','SeriesDate'];
        $this->dict[0x0008][0x0022] = ['DA','1','AcquisitionDate'];
        $this->dict[0x0008][0x0023] = ['DA','1','ImageDate'];
        $this->dict[0x0008][0x0024] = ['DA','1','OverlayDate'];
        $this->dict[0x0008][0x0025] = ['DA','1','CurveDate'];
        $this->dict[0x0008][0x002A] = ['DT','1','AcquisitionDatetime'];
        $this->dict[0x0008][0x0030] = ['TM','1','StudyTime'];
        $this->dict[0x0008][0x0031] = ['TM','1','SeriesTime'];
        $this->dict[0x0008][0x0032] = ['TM','1','AcquisitionTime'];
        $this->dict[0x0008][0x0033] = ['TM','1','ImageTime'];
        $this->dict[0x0008][0x0034] = ['TM','1','OverlayTime'];
        $this->dict[0x0008][0x0035] = ['TM','1','CurveTime'];
        $this->dict[0x0008][0x0040] = ['US','1','OldDataSetType'];
        $this->dict[0x0008][0x0041] = ['LT','1','OldDataSetSubtype'];
        $this->dict[0x0008][0x0042] = ['CS','1','NuclearMedicineSeriesType'];
        $this->dict[0x0008][0x0050] = ['SH','1','AccessionNumber'];
        $this->dict[0x0008][0x0052] = ['CS','1','QueryRetrieveLevel'];
        $this->dict[0x0008][0x0054] = ['AE','1-n','RetrieveAETitle'];
        $this->dict[0x0008][0x0058] = ['UI','1-n','DataSetFailedSOPInstanceUIDList'];
        $this->dict[0x0008][0x0060] = ['CS','1','Modality'];
        $this->dict[0x0008][0x0061] = ['CS','1-n','ModalitiesInStudy'];
        $this->dict[0x0008][0x0064] = ['CS','1','ConversionType'];
        $this->dict[0x0008][0x0068] = ['CS','1','PresentationIntentType'];
        $this->dict[0x0008][0x0070] = ['LO','1','Manufacturer'];
        $this->dict[0x0008][0x0080] = ['LO','1','InstitutionName'];
        $this->dict[0x0008][0x0081] = ['ST','1','InstitutionAddress'];
        $this->dict[0x0008][0x0082] = ['SQ','1','InstitutionCodeSequence'];
        $this->dict[0x0008][0x0090] = ['PN','1','ReferringPhysicianName'];
        $this->dict[0x0008][0x0092] = ['ST','1','ReferringPhysicianAddress'];
        $this->dict[0x0008][0x0094] = ['SH','1-n','ReferringPhysicianTelephoneNumber'];
        $this->dict[0x0008][0x0100] = ['SH','1','CodeValue'];
        $this->dict[0x0008][0x0102] = ['SH','1','CodingSchemeDesignator'];
        $this->dict[0x0008][0x0103] = ['SH','1','CodingSchemeVersion'];
        $this->dict[0x0008][0x0104] = ['LO','1','CodeMeaning'];
        $this->dict[0x0008][0x0105] = ['CS','1','MappingResource'];
        $this->dict[0x0008][0x0106] = ['DT','1','ContextGroupVersion'];
        $this->dict[0x0008][0x0107] = ['DT','1','ContextGroupLocalVersion'];
        $this->dict[0x0008][0x010B] = ['CS','1','CodeSetExtensionFlag'];
        $this->dict[0x0008][0x010C] = ['UI','1','PrivateCodingSchemeCreatorUID'];
        $this->dict[0x0008][0x010D] = ['UI','1','CodeSetExtensionCreatorUID'];
        $this->dict[0x0008][0x010F] = ['CS','1','ContextIdentifier'];
        $this->dict[0x0008][0x0201] = ['SH','1','TimezoneOffsetFromUTC'];
        $this->dict[0x0008][0x1000] = ['AE','1','NetworkID'];
        $this->dict[0x0008][0x1010] = ['SH','1','StationName'];
        $this->dict[0x0008][0x1030] = ['LO','1','StudyDescription'];
        $this->dict[0x0008][0x1032] = ['SQ','1','ProcedureCodeSequence'];
        $this->dict[0x0008][0x103E] = ['LO','1','SeriesDescription'];
        $this->dict[0x0008][0x1040] = ['LO','1','InstitutionalDepartmentName'];
        $this->dict[0x0008][0x1048] = ['PN','1-n','PhysicianOfRecord'];
        $this->dict[0x0008][0x1050] = ['PN','1-n','PerformingPhysicianName'];
        $this->dict[0x0008][0x1060] = ['PN','1-n','PhysicianReadingStudy'];
        $this->dict[0x0008][0x1070] = ['PN','1-n','OperatorName'];
        $this->dict[0x0008][0x1080] = ['LO','1-n','AdmittingDiagnosisDescription'];
        $this->dict[0x0008][0x1084] = ['SQ','1','AdmittingDiagnosisCodeSequence'];
        $this->dict[0x0008][0x1090] = ['LO','1','ManufacturerModelName'];
        $this->dict[0x0008][0x1100] = ['SQ','1','ReferencedResultsSequence'];
        $this->dict[0x0008][0x1110] = ['SQ','1','ReferencedStudySequence'];
        $this->dict[0x0008][0x1111] = ['SQ','1','ReferencedStudyComponentSequence'];
        $this->dict[0x0008][0x1115] = ['SQ','1','ReferencedSeriesSequence'];
        $this->dict[0x0008][0x1120] = ['SQ','1','ReferencedPatientSequence'];
        $this->dict[0x0008][0x1125] = ['SQ','1','ReferencedVisitSequence'];
        $this->dict[0x0008][0x1130] = ['SQ','1','ReferencedOverlaySequence'];
        $this->dict[0x0008][0x1140] = ['SQ','1','ReferencedImageSequence'];
        $this->dict[0x0008][0x1145] = ['SQ','1','ReferencedCurveSequence'];
        $this->dict[0x0008][0x114A] = ['SQ','1','ReferencedInstanceSequence'];
        $this->dict[0x0008][0x114B] = ['LO','1','ReferenceDescription'];
        $this->dict[0x0008][0x1150] = ['UI','1','ReferencedSOPClassUID'];
        $this->dict[0x0008][0x1155] = ['UI','1','ReferencedSOPInstanceUID'];
        $this->dict[0x0008][0x115A] = ['UI','1-n','SOPClassesSupported'];
        $this->dict[0x0008][0x1160] = ['IS','1','ReferencedFrameNumber'];
        $this->dict[0x0008][0x1195] = ['UI','1','TransactionUID'];
        $this->dict[0x0008][0x1197] = ['US','1','FailureReason'];
        $this->dict[0x0008][0x1198] = ['SQ','1','FailedSOPSequence'];
        $this->dict[0x0008][0x1199] = ['SQ','1','ReferencedSOPSequence'];
        $this->dict[0x0008][0x2110] = ['CS','1','LossyImageCompression'];
        $this->dict[0x0008][0x2111] = ['ST','1','DerivationDescription'];
        $this->dict[0x0008][0x2112] = ['SQ','1','SourceImageSequence'];
        $this->dict[0x0008][0x2120] = ['SH','1','StageName'];
        $this->dict[0x0008][0x2122] = ['IS','1','StageNumber'];
        $this->dict[0x0008][0x2124] = ['IS','1','NumberOfStages'];
        $this->dict[0x0008][0x2128] = ['IS','1','ViewNumber'];
        $this->dict[0x0008][0x2129] = ['IS','1','NumberOfEventTimers'];
        $this->dict[0x0008][0x212A] = ['IS','1','NumberOfViewsInStage'];
        $this->dict[0x0008][0x2130] = ['DS','1-n','EventElapsedTime'];
        $this->dict[0x0008][0x2132] = ['LO','1-n','EventTimerName'];
        $this->dict[0x0008][0x2142] = ['IS','1','StartTrim'];
        $this->dict[0x0008][0x2143] = ['IS','1','StopTrim'];
        $this->dict[0x0008][0x2144] = ['IS','1','RecommendedDisplayFrameRate'];
        $this->dict[0x0008][0x2200] = ['CS','1','TransducerPosition'];
        $this->dict[0x0008][0x2204] = ['CS','1','TransducerOrientation'];
        $this->dict[0x0008][0x2208] = ['CS','1','AnatomicStructure'];
        $this->dict[0x0008][0x2218] = ['SQ','1','AnatomicRegionSequence'];
        $this->dict[0x0008][0x2220] = ['SQ','1','AnatomicRegionModifierSequence'];
        $this->dict[0x0008][0x2228] = ['SQ','1','PrimaryAnatomicStructureSequence'];
        $this->dict[0x0008][0x2229] = ['SQ','1','AnatomicStructureSpaceOrRegionSequence'];
        $this->dict[0x0008][0x2230] = ['SQ','1','PrimaryAnatomicStructureModifierSequence'];
        $this->dict[0x0008][0x2240] = ['SQ','1','TransducerPositionSequence'];
        $this->dict[0x0008][0x2242] = ['SQ','1','TransducerPositionModifierSequence'];
        $this->dict[0x0008][0x2244] = ['SQ','1','TransducerOrientationSequence'];
        $this->dict[0x0008][0x2246] = ['SQ','1','TransducerOrientationModifierSequence'];
        $this->dict[0x0008][0x4000] = ['LT','1-n','IdentifyingComments'];
        $this->dict[0x0010][0x0000] = ['UL','1','PatientGroupLength'];
        $this->dict[0x0010][0x0010] = ['PN','1','PatientName'];
        $this->dict[0x0010][0x0020] = ['LO','1','PatientID'];
        $this->dict[0x0010][0x0021] = ['LO','1','IssuerOfPatientID'];
        $this->dict[0x0010][0x0030] = ['DA','1','PatientBirthDate'];
        $this->dict[0x0010][0x0032] = ['TM','1','PatientBirthTime'];
        $this->dict[0x0010][0x0040] = ['CS','1','PatientSex'];
        $this->dict[0x0010][0x0050] = ['SQ','1','PatientInsurancePlanCodeSequence'];
        $this->dict[0x0010][0x1000] = ['LO','1-n','OtherPatientID'];
        $this->dict[0x0010][0x1001] = ['PN','1-n','OtherPatientName'];
        $this->dict[0x0010][0x1005] = ['PN','1','PatientBirthName'];
        $this->dict[0x0010][0x1010] = ['AS','1','PatientAge'];
        $this->dict[0x0010][0x1020] = ['DS','1','PatientSize'];
        $this->dict[0x0010][0x1030] = ['DS','1','PatientWeight'];
        $this->dict[0x0010][0x1040] = ['LO','1','PatientAddress'];
        $this->dict[0x0010][0x1050] = ['LT','1-n','InsurancePlanIdentification'];
        $this->dict[0x0010][0x1060] = ['PN','1','PatientMotherBirthName'];
        $this->dict[0x0010][0x1080] = ['LO','1','MilitaryRank'];
        $this->dict[0x0010][0x1081] = ['LO','1','BranchOfService'];
        $this->dict[0x0010][0x1090] = ['LO','1','MedicalRecordLocator'];
        $this->dict[0x0010][0x2000] = ['LO','1-n','MedicalAlerts'];
        $this->dict[0x0010][0x2110] = ['LO','1-n','ContrastAllergies'];
        $this->dict[0x0010][0x2150] = ['LO','1','CountryOfResidence'];
        $this->dict[0x0010][0x2152] = ['LO','1','RegionOfResidence'];
        $this->dict[0x0010][0x2154] = ['SH','1-n','PatientTelephoneNumber'];
        $this->dict[0x0010][0x2160] = ['SH','1','EthnicGroup'];
        $this->dict[0x0010][0x2180] = ['SH','1','Occupation'];
        $this->dict[0x0010][0x21A0] = ['CS','1','SmokingStatus'];
        $this->dict[0x0010][0x21B0] = ['LT','1','AdditionalPatientHistory'];
        $this->dict[0x0010][0x21C0] = ['US','1','PregnancyStatus'];
        $this->dict[0x0010][0x21D0] = ['DA','1','LastMenstrualDate'];
        $this->dict[0x0010][0x21F0] = ['LO','1','PatientReligiousPreference'];
        $this->dict[0x0010][0x4000] = ['LT','1','PatientComments'];
        $this->dict[0x0018][0x0000] = ['UL','1','AcquisitionGroupLength'];
        $this->dict[0x0018][0x0010] = ['LO','1','ContrastBolusAgent'];
        $this->dict[0x0018][0x0012] = ['SQ','1','ContrastBolusAgentSequence'];
        $this->dict[0x0018][0x0014] = ['SQ','1','ContrastBolusAdministrationRouteSequence'];
        $this->dict[0x0018][0x0015] = ['CS','1','BodyPartExamined'];
        $this->dict[0x0018][0x0020] = ['CS','1-n','ScanningSequence'];
        $this->dict[0x0018][0x0021] = ['CS','1-n','SequenceVariant'];
        $this->dict[0x0018][0x0022] = ['CS','1-n','ScanOptions'];
        $this->dict[0x0018][0x0023] = ['CS','1','MRAcquisitionType'];
        $this->dict[0x0018][0x0024] = ['SH','1','SequenceName'];
        $this->dict[0x0018][0x0025] = ['CS','1','AngioFlag'];
        $this->dict[0x0018][0x0026] = ['SQ','1','InterventionDrugInformationSequence'];
        $this->dict[0x0018][0x0027] = ['TM','1','InterventionDrugStopTime'];
        $this->dict[0x0018][0x0028] = ['DS','1','InterventionDrugDose'];
        $this->dict[0x0018][0x0029] = ['SQ','1','InterventionalDrugSequence'];
        $this->dict[0x0018][0x002A] = ['SQ','1','AdditionalDrugSequence'];
        $this->dict[0x0018][0x0030] = ['LO','1-n','Radionuclide'];
        $this->dict[0x0018][0x0031] = ['LO','1-n','Radiopharmaceutical'];
        $this->dict[0x0018][0x0032] = ['DS','1','EnergyWindowCenterline'];
        $this->dict[0x0018][0x0033] = ['DS','1-n','EnergyWindowTotalWidth'];
        $this->dict[0x0018][0x0034] = ['LO','1','InterventionalDrugName'];
        $this->dict[0x0018][0x0035] = ['TM','1','InterventionalDrugStartTime'];
        $this->dict[0x0018][0x0036] = ['SQ','1','InterventionalTherapySequence'];
        $this->dict[0x0018][0x0037] = ['CS','1','TherapyType'];
        $this->dict[0x0018][0x0038] = ['CS','1','InterventionalStatus'];
        $this->dict[0x0018][0x0039] = ['CS','1','TherapyDescription'];
        $this->dict[0x0018][0x0040] = ['IS','1','CineRate'];
        $this->dict[0x0018][0x0050] = ['DS','1','SliceThickness'];
        $this->dict[0x0018][0x0060] = ['DS','1','KVP'];
        $this->dict[0x0018][0x0070] = ['IS','1','CountsAccumulated'];
        $this->dict[0x0018][0x0071] = ['CS','1','AcquisitionTerminationCondition'];
        $this->dict[0x0018][0x0072] = ['DS','1','EffectiveSeriesDuration'];
        $this->dict[0x0018][0x0073] = ['CS','1','AcquisitionStartCondition'];
        $this->dict[0x0018][0x0074] = ['IS','1','AcquisitionStartConditionData'];
        $this->dict[0x0018][0x0075] = ['IS','1','AcquisitionTerminationConditionData'];
        $this->dict[0x0018][0x0080] = ['DS','1','RepetitionTime'];
        $this->dict[0x0018][0x0081] = ['DS','1','EchoTime'];
        $this->dict[0x0018][0x0082] = ['DS','1','InversionTime'];
        $this->dict[0x0018][0x0083] = ['DS','1','NumberOfAverages'];
        $this->dict[0x0018][0x0084] = ['DS','1','ImagingFrequency'];
        $this->dict[0x0018][0x0085] = ['SH','1','ImagedNucleus'];
        $this->dict[0x0018][0x0086] = ['IS','1-n','EchoNumber'];
        $this->dict[0x0018][0x0087] = ['DS','1','MagneticFieldStrength'];
        $this->dict[0x0018][0x0088] = ['DS','1','SpacingBetweenSlices'];
        $this->dict[0x0018][0x0089] = ['IS','1','NumberOfPhaseEncodingSteps'];
        $this->dict[0x0018][0x0090] = ['DS','1','DataCollectionDiameter'];
        $this->dict[0x0018][0x0091] = ['IS','1','EchoTrainLength'];
        $this->dict[0x0018][0x0093] = ['DS','1','PercentSampling'];
        $this->dict[0x0018][0x0094] = ['DS','1','PercentPhaseFieldOfView'];
        $this->dict[0x0018][0x0095] = ['DS','1','PixelBandwidth'];
        $this->dict[0x0018][0x1000] = ['LO','1','DeviceSerialNumber'];
        $this->dict[0x0018][0x1004] = ['LO','1','PlateID'];
        $this->dict[0x0018][0x1010] = ['LO','1','SecondaryCaptureDeviceID'];
        $this->dict[0x0018][0x1011] = ['LO','1','HardcopyCreationDeviceID'];
        $this->dict[0x0018][0x1012] = ['DA','1','DateOfSecondaryCapture'];
        $this->dict[0x0018][0x1014] = ['TM','1','TimeOfSecondaryCapture'];
        $this->dict[0x0018][0x1016] = ['LO','1','SecondaryCaptureDeviceManufacturer'];
        $this->dict[0x0018][0x1017] = ['LO','1','HardcopyDeviceManufacturer'];
        $this->dict[0x0018][0x1018] = ['LO','1','SecondaryCaptureDeviceManufacturerModelName'];
        $this->dict[0x0018][0x1019] = ['LO','1-n','SecondaryCaptureDeviceSoftwareVersion'];
        $this->dict[0x0018][0x101A] = ['LO','1-n','HardcopyDeviceSoftwareVersion'];
        $this->dict[0x0018][0x101B] = ['LO','1','HardcopyDeviceManfuacturersModelName'];
        $this->dict[0x0018][0x1020] = ['LO','1-n','SoftwareVersion'];
        $this->dict[0x0018][0x1022] = ['SH','1','VideoImageFormatAcquired'];
        $this->dict[0x0018][0x1023] = ['LO','1','DigitalImageFormatAcquired'];
        $this->dict[0x0018][0x1030] = ['LO','1','ProtocolName'];
        $this->dict[0x0018][0x1040] = ['LO','1','ContrastBolusRoute'];
        $this->dict[0x0018][0x1041] = ['DS','1','ContrastBolusVolume'];
        $this->dict[0x0018][0x1042] = ['TM','1','ContrastBolusStartTime'];
        $this->dict[0x0018][0x1043] = ['TM','1','ContrastBolusStopTime'];
        $this->dict[0x0018][0x1044] = ['DS','1','ContrastBolusTotalDose'];
        $this->dict[0x0018][0x1045] = ['IS','1-n','SyringeCounts'];
        $this->dict[0x0018][0x1046] = ['DS','1-n','ContrastFlowRate'];
        $this->dict[0x0018][0x1047] = ['DS','1-n','ContrastFlowDuration'];
        $this->dict[0x0018][0x1048] = ['CS','1','ContrastBolusIngredient'];
        $this->dict[0x0018][0x1049] = ['DS','1','ContrastBolusIngredientConcentration'];
        $this->dict[0x0018][0x1050] = ['DS','1','SpatialResolution'];
        $this->dict[0x0018][0x1060] = ['DS','1','TriggerTime'];
        $this->dict[0x0018][0x1061] = ['LO','1','TriggerSourceOrType'];
        $this->dict[0x0018][0x1062] = ['IS','1','NominalInterval'];
        $this->dict[0x0018][0x1063] = ['DS','1','FrameTime'];
        $this->dict[0x0018][0x1064] = ['LO','1','FramingType'];
        $this->dict[0x0018][0x1065] = ['DS','1-n','FrameTimeVector'];
        $this->dict[0x0018][0x1066] = ['DS','1','FrameDelay'];
        $this->dict[0x0018][0x1067] = ['DS','1','ImageTriggerDelay'];
        $this->dict[0x0018][0x1068] = ['DS','1','MultiplexGroupTimeOffset'];
        $this->dict[0x0018][0x1069] = ['DS','1','TriggerTimeOffset'];
        $this->dict[0x0018][0x106A] = ['CS','1','SynchronizationTrigger'];
        $this->dict[0x0018][0x106C] = ['US','2','SynchronizationChannel'];
        $this->dict[0x0018][0x106E] = ['UL','1','TriggerSamplePosition'];
        $this->dict[0x0018][0x1070] = ['LO','1-n','RadionuclideRoute'];
        $this->dict[0x0018][0x1071] = ['DS','1-n','RadionuclideVolume'];
        $this->dict[0x0018][0x1072] = ['TM','1-n','RadionuclideStartTime'];
        $this->dict[0x0018][0x1073] = ['TM','1-n','RadionuclideStopTime'];
        $this->dict[0x0018][0x1074] = ['DS','1-n','RadionuclideTotalDose'];
        $this->dict[0x0018][0x1075] = ['DS','1','RadionuclideHalfLife'];
        $this->dict[0x0018][0x1076] = ['DS','1','RadionuclidePositronFraction'];
        $this->dict[0x0018][0x1077] = ['DS','1','RadiopharmaceuticalSpecificActivity'];
        $this->dict[0x0018][0x1080] = ['CS','1','BeatRejectionFlag'];
        $this->dict[0x0018][0x1081] = ['IS','1','LowRRValue'];
        $this->dict[0x0018][0x1082] = ['IS','1','HighRRValue'];
        $this->dict[0x0018][0x1083] = ['IS','1','IntervalsAcquired'];
        $this->dict[0x0018][0x1084] = ['IS','1','IntervalsRejected'];
        $this->dict[0x0018][0x1085] = ['LO','1','PVCRejection'];
        $this->dict[0x0018][0x1086] = ['IS','1','SkipBeats'];
        $this->dict[0x0018][0x1088] = ['IS','1','HeartRate'];
        $this->dict[0x0018][0x1090] = ['IS','1','CardiacNumberOfImages'];
        $this->dict[0x0018][0x1094] = ['IS','1','TriggerWindow'];
        $this->dict[0x0018][0x1100] = ['DS','1','ReconstructionDiameter'];
        $this->dict[0x0018][0x1110] = ['DS','1','DistanceSourceToDetector'];
        $this->dict[0x0018][0x1111] = ['DS','1','DistanceSourceToPatient'];
        $this->dict[0x0018][0x1114] = ['DS','1','EstimatedRadiographicMagnificationFactor'];
        $this->dict[0x0018][0x1120] = ['DS','1','GantryDetectorTilt'];
        $this->dict[0x0018][0x1121] = ['DS','1','GantryDetectorSlew'];
        $this->dict[0x0018][0x1130] = ['DS','1','TableHeight'];
        $this->dict[0x0018][0x1131] = ['DS','1','TableTraverse'];
        $this->dict[0x0018][0x1134] = ['DS','1','TableMotion'];
        $this->dict[0x0018][0x1135] = ['DS','1-n','TableVerticalIncrement'];
        $this->dict[0x0018][0x1136] = ['DS','1-n','TableLateralIncrement'];
        $this->dict[0x0018][0x1137] = ['DS','1-n','TableLongitudinalIncrement'];
        $this->dict[0x0018][0x1138] = ['DS','1','TableAngle'];
        $this->dict[0x0018][0x113A] = ['CS','1','TableType'];
        $this->dict[0x0018][0x1140] = ['CS','1','RotationDirection'];
        $this->dict[0x0018][0x1141] = ['DS','1','AngularPosition'];
        $this->dict[0x0018][0x1142] = ['DS','1-n','RadialPosition'];
        $this->dict[0x0018][0x1143] = ['DS','1','ScanArc'];
        $this->dict[0x0018][0x1144] = ['DS','1','AngularStep'];
        $this->dict[0x0018][0x1145] = ['DS','1','CenterOfRotationOffset'];
        $this->dict[0x0018][0x1146] = ['DS','1-n','RotationOffset'];
        $this->dict[0x0018][0x1147] = ['CS','1','FieldOfViewShape'];
        $this->dict[0x0018][0x1149] = ['IS','2','FieldOfViewDimension'];
        $this->dict[0x0018][0x1150] = ['IS','1','ExposureTime'];
        $this->dict[0x0018][0x1151] = ['IS','1','XrayTubeCurrent'];
        $this->dict[0x0018][0x1152] = ['IS','1','Exposure'];
        $this->dict[0x0018][0x1153] = ['IS','1','ExposureinuAs'];
        $this->dict[0x0018][0x1154] = ['DS','1','AveragePulseWidth'];
        $this->dict[0x0018][0x1155] = ['CS','1','RadiationSetting'];
        $this->dict[0x0018][0x1156] = ['CS','1','RectificationType'];
        $this->dict[0x0018][0x115A] = ['CS','1','RadiationMode'];
        $this->dict[0x0018][0x115E] = ['DS','1','ImageAreaDoseProduct'];
        $this->dict[0x0018][0x1160] = ['SH','1','FilterType'];
        $this->dict[0x0018][0x1161] = ['LO','1-n','TypeOfFilters'];
        $this->dict[0x0018][0x1162] = ['DS','1','IntensifierSize'];
        $this->dict[0x0018][0x1164] = ['DS','2','ImagerPixelSpacing'];
        $this->dict[0x0018][0x1166] = ['CS','1','Grid'];
        $this->dict[0x0018][0x1170] = ['IS','1','GeneratorPower'];
        $this->dict[0x0018][0x1180] = ['SH','1','CollimatorGridName'];
        $this->dict[0x0018][0x1181] = ['CS','1','CollimatorType'];
        $this->dict[0x0018][0x1182] = ['IS','1','FocalDistance'];
        $this->dict[0x0018][0x1183] = ['DS','1','XFocusCenter'];
        $this->dict[0x0018][0x1184] = ['DS','1','YFocusCenter'];
        $this->dict[0x0018][0x1190] = ['DS','1-n','FocalSpot'];
        $this->dict[0x0018][0x1191] = ['CS','1','AnodeTargetMaterial'];
        $this->dict[0x0018][0x11A0] = ['DS','1','BodyPartThickness'];
        $this->dict[0x0018][0x11A2] = ['DS','1','CompressionForce'];
        $this->dict[0x0018][0x1200] = ['DA','1-n','DateOfLastCalibration'];
        $this->dict[0x0018][0x1201] = ['TM','1-n','TimeOfLastCalibration'];
        $this->dict[0x0018][0x1210] = ['SH','1-n','ConvolutionKernel'];
        $this->dict[0x0018][0x1240] = ['IS','1-n','UpperLowerPixelValues'];
        $this->dict[0x0018][0x1242] = ['IS','1','ActualFrameDuration'];
        $this->dict[0x0018][0x1243] = ['IS','1','CountRate'];
        $this->dict[0x0018][0x1244] = ['US','1','PreferredPlaybackSequencing'];
        $this->dict[0x0018][0x1250] = ['SH','1','ReceivingCoil'];
        $this->dict[0x0018][0x1251] = ['SH','1','TransmittingCoil'];
        $this->dict[0x0018][0x1260] = ['SH','1','PlateType'];
        $this->dict[0x0018][0x1261] = ['LO','1','PhosphorType'];
        $this->dict[0x0018][0x1300] = ['IS','1','ScanVelocity'];
        $this->dict[0x0018][0x1301] = ['CS','1-n','WholeBodyTechnique'];
        $this->dict[0x0018][0x1302] = ['IS','1','ScanLength'];
        $this->dict[0x0018][0x1310] = ['US','4','AcquisitionMatrix'];
        $this->dict[0x0018][0x1312] = ['CS','1','PhaseEncodingDirection'];
        $this->dict[0x0018][0x1314] = ['DS','1','FlipAngle'];
        $this->dict[0x0018][0x1315] = ['CS','1','VariableFlipAngleFlag'];
        $this->dict[0x0018][0x1316] = ['DS','1','SAR'];
        $this->dict[0x0018][0x1318] = ['DS','1','dBdt'];
        $this->dict[0x0018][0x1400] = ['LO','1','AcquisitionDeviceProcessingDescription'];
        $this->dict[0x0018][0x1401] = ['LO','1','AcquisitionDeviceProcessingCode'];
        $this->dict[0x0018][0x1402] = ['CS','1','CassetteOrientation'];
        $this->dict[0x0018][0x1403] = ['CS','1','CassetteSize'];
        $this->dict[0x0018][0x1404] = ['US','1','ExposuresOnPlate'];
        $this->dict[0x0018][0x1405] = ['IS','1','RelativeXrayExposure'];
        $this->dict[0x0018][0x1450] = ['DS','1','ColumnAngulation'];
        $this->dict[0x0018][0x1460] = ['DS','1','TomoLayerHeight'];
        $this->dict[0x0018][0x1470] = ['DS','1','TomoAngle'];
        $this->dict[0x0018][0x1480] = ['DS','1','TomoTime'];
        $this->dict[0x0018][0x1490] = ['CS','1','TomoType'];
        $this->dict[0x0018][0x1491] = ['CS','1','TomoClass'];
        $this->dict[0x0018][0x1495] = ['IS','1','NumberofTomosynthesisSourceImages'];
        $this->dict[0x0018][0x1500] = ['CS','1','PositionerMotion'];
        $this->dict[0x0018][0x1508] = ['CS','1','PositionerType'];
        $this->dict[0x0018][0x1510] = ['DS','1','PositionerPrimaryAngle'];
        $this->dict[0x0018][0x1511] = ['DS','1','PositionerSecondaryAngle'];
        $this->dict[0x0018][0x1520] = ['DS','1-n','PositionerPrimaryAngleIncrement'];
        $this->dict[0x0018][0x1521] = ['DS','1-n','PositionerSecondaryAngleIncrement'];
        $this->dict[0x0018][0x1530] = ['DS','1','DetectorPrimaryAngle'];
        $this->dict[0x0018][0x1531] = ['DS','1','DetectorSecondaryAngle'];
        $this->dict[0x0018][0x1600] = ['CS','3','ShutterShape'];
        $this->dict[0x0018][0x1602] = ['IS','1','ShutterLeftVerticalEdge'];
        $this->dict[0x0018][0x1604] = ['IS','1','ShutterRightVerticalEdge'];
        $this->dict[0x0018][0x1606] = ['IS','1','ShutterUpperHorizontalEdge'];
        $this->dict[0x0018][0x1608] = ['IS','1','ShutterLowerHorizontalEdge'];
        $this->dict[0x0018][0x1610] = ['IS','1','CenterOfCircularShutter'];
        $this->dict[0x0018][0x1612] = ['IS','1','RadiusOfCircularShutter'];
        $this->dict[0x0018][0x1620] = ['IS','1-n','VerticesOfPolygonalShutter'];
        $this->dict[0x0018][0x1622] = ['US','1','ShutterPresentationValue'];
        $this->dict[0x0018][0x1623] = ['US','1','ShutterOverlayGroup'];
        $this->dict[0x0018][0x1700] = ['CS','3','CollimatorShape'];
        $this->dict[0x0018][0x1702] = ['IS','1','CollimatorLeftVerticalEdge'];
        $this->dict[0x0018][0x1704] = ['IS','1','CollimatorRightVerticalEdge'];
        $this->dict[0x0018][0x1706] = ['IS','1','CollimatorUpperHorizontalEdge'];
        $this->dict[0x0018][0x1708] = ['IS','1','CollimatorLowerHorizontalEdge'];
        $this->dict[0x0018][0x1710] = ['IS','1','CenterOfCircularCollimator'];
        $this->dict[0x0018][0x1712] = ['IS','1','RadiusOfCircularCollimator'];
        $this->dict[0x0018][0x1720] = ['IS','1-n','VerticesOfPolygonalCollimator'];
        $this->dict[0x0018][0x1800] = ['CS','1','AcquisitionTimeSynchronized'];
        $this->dict[0x0018][0x1801] = ['SH','1','TimeSource'];
        $this->dict[0x0018][0x1802] = ['CS','1','TimeDistributionProtocol'];
        $this->dict[0x0018][0x1810] = ['DT','1','AcquisitionTimestamp'];
        $this->dict[0x0018][0x4000] = ['LT','1-n','AcquisitionComments'];
        $this->dict[0x0018][0x5000] = ['SH','1-n','OutputPower'];
        $this->dict[0x0018][0x5010] = ['LO','3','TransducerData'];
        $this->dict[0x0018][0x5012] = ['DS','1','FocusDepth'];
        $this->dict[0x0018][0x5020] = ['LO','1','PreprocessingFunction'];
        $this->dict[0x0018][0x5021] = ['LO','1','PostprocessingFunction'];
        $this->dict[0x0018][0x5022] = ['DS','1','MechanicalIndex'];
        $this->dict[0x0018][0x5024] = ['DS','1','ThermalIndex'];
        $this->dict[0x0018][0x5026] = ['DS','1','CranialThermalIndex'];
        $this->dict[0x0018][0x5027] = ['DS','1','SoftTissueThermalIndex'];
        $this->dict[0x0018][0x5028] = ['DS','1','SoftTissueFocusThermalIndex'];
        $this->dict[0x0018][0x5029] = ['DS','1','SoftTissueSurfaceThermalIndex'];
        $this->dict[0x0018][0x5030] = ['DS','1','DynamicRange'];
        $this->dict[0x0018][0x5040] = ['DS','1','TotalGain'];
        $this->dict[0x0018][0x5050] = ['IS','1','DepthOfScanField'];
        $this->dict[0x0018][0x5100] = ['CS','1','PatientPosition'];
        $this->dict[0x0018][0x5101] = ['CS','1','ViewPosition'];
        $this->dict[0x0018][0x5104] = ['SQ','1','ProjectionEponymousNameCodeSequence'];
        $this->dict[0x0018][0x5210] = ['DS','6','ImageTransformationMatrix'];
        $this->dict[0x0018][0x5212] = ['DS','3','ImageTranslationVector'];
        $this->dict[0x0018][0x6000] = ['DS','1','Sensitivity'];
        $this->dict[0x0018][0x6011] = ['SQ','1','SequenceOfUltrasoundRegions'];
        $this->dict[0x0018][0x6012] = ['US','1','RegionSpatialFormat'];
        $this->dict[0x0018][0x6014] = ['US','1','RegionDataType'];
        $this->dict[0x0018][0x6016] = ['UL','1','RegionFlags'];
        $this->dict[0x0018][0x6018] = ['UL','1','RegionLocationMinX0'];
        $this->dict[0x0018][0x601A] = ['UL','1','RegionLocationMinY0'];
        $this->dict[0x0018][0x601C] = ['UL','1','RegionLocationMaxX1'];
        $this->dict[0x0018][0x601E] = ['UL','1','RegionLocationMaxY1'];
        $this->dict[0x0018][0x6020] = ['SL','1','ReferencePixelX0'];
        $this->dict[0x0018][0x6022] = ['SL','1','ReferencePixelY0'];
        $this->dict[0x0018][0x6024] = ['US','1','PhysicalUnitsXDirection'];
        $this->dict[0x0018][0x6026] = ['US','1','PhysicalUnitsYDirection'];
        $this->dict[0x0018][0x6028] = ['FD','1','ReferencePixelPhysicalValueX'];
        $this->dict[0x0018][0x602A] = ['FD','1','ReferencePixelPhysicalValueY'];
        $this->dict[0x0018][0x602C] = ['FD','1','PhysicalDeltaX'];
        $this->dict[0x0018][0x602E] = ['FD','1','PhysicalDeltaY'];
        $this->dict[0x0018][0x6030] = ['UL','1','TransducerFrequency'];
        $this->dict[0x0018][0x6031] = ['CS','1','TransducerType'];
        $this->dict[0x0018][0x6032] = ['UL','1','PulseRepetitionFrequency'];
        $this->dict[0x0018][0x6034] = ['FD','1','DopplerCorrectionAngle'];
        $this->dict[0x0018][0x6036] = ['FD','1','SteeringAngle'];
        $this->dict[0x0018][0x6038] = ['UL','1','DopplerSampleVolumeXPosition'];
        $this->dict[0x0018][0x603A] = ['UL','1','DopplerSampleVolumeYPosition'];
        $this->dict[0x0018][0x603C] = ['UL','1','TMLinePositionX0'];
        $this->dict[0x0018][0x603E] = ['UL','1','TMLinePositionY0'];
        $this->dict[0x0018][0x6040] = ['UL','1','TMLinePositionX1'];
        $this->dict[0x0018][0x6042] = ['UL','1','TMLinePositionY1'];
        $this->dict[0x0018][0x6044] = ['US','1','PixelComponentOrganization'];
        $this->dict[0x0018][0x6046] = ['UL','1','PixelComponentMask'];
        $this->dict[0x0018][0x6048] = ['UL','1','PixelComponentRangeStart'];
        $this->dict[0x0018][0x604A] = ['UL','1','PixelComponentRangeStop'];
        $this->dict[0x0018][0x604C] = ['US','1','PixelComponentPhysicalUnits'];
        $this->dict[0x0018][0x604E] = ['US','1','PixelComponentDataType'];
        $this->dict[0x0018][0x6050] = ['UL','1','NumberOfTableBreakPoints'];
        $this->dict[0x0018][0x6052] = ['UL','1-n','TableOfXBreakPoints'];
        $this->dict[0x0018][0x6054] = ['FD','1-n','TableOfYBreakPoints'];
        $this->dict[0x0018][0x6056] = ['UL','1','NumberOfTableEntries'];
        $this->dict[0x0018][0x6058] = ['UL','1-n','TableOfPixelValues'];
        $this->dict[0x0018][0x605A] = ['FL','1-n','TableOfParameterValues'];
        $this->dict[0x0018][0x7000] = ['CS','1','DetectorConditionsNominalFlag'];
        $this->dict[0x0018][0x7001] = ['DS','1','DetectorTemperature'];
        $this->dict[0x0018][0x7004] = ['CS','1','DetectorType'];
        $this->dict[0x0018][0x7005] = ['CS','1','DetectorConfiguration'];
        $this->dict[0x0018][0x7006] = ['LT','1','DetectorDescription'];
        $this->dict[0x0018][0x7008] = ['LT','1','DetectorMode'];
        $this->dict[0x0018][0x700A] = ['SH','1','DetectorID'];
        $this->dict[0x0018][0x700C] = ['DA','1','DateofLastDetectorCalibration'];
        $this->dict[0x0018][0x700E] = ['TM','1','TimeofLastDetectorCalibration'];
        $this->dict[0x0018][0x7010] = ['IS','1','ExposuresOnDetectorSinceLastCalibration'];
        $this->dict[0x0018][0x7011] = ['IS','1','ExposuresOnDetectorSinceManufactured'];
        $this->dict[0x0018][0x7012] = ['DS','1','DetectorTimeSinceLastExposure'];
        $this->dict[0x0018][0x7014] = ['DS','1','DetectorActiveTime'];
        $this->dict[0x0018][0x7016] = ['DS','1','DetectorActivationOffsetFromExposure'];
        $this->dict[0x0018][0x701A] = ['DS','2','DetectorBinning'];
        $this->dict[0x0018][0x7020] = ['DS','2','DetectorElementPhysicalSize'];
        $this->dict[0x0018][0x7022] = ['DS','2','DetectorElementSpacing'];
        $this->dict[0x0018][0x7024] = ['CS','1','DetectorActiveShape'];
        $this->dict[0x0018][0x7026] = ['DS','1-2','DetectorActiveDimensions'];
        $this->dict[0x0018][0x7028] = ['DS','2','DetectorActiveOrigin'];
        $this->dict[0x0018][0x7030] = ['DS','2','FieldofViewOrigin'];
        $this->dict[0x0018][0x7032] = ['DS','1','FieldofViewRotation'];
        $this->dict[0x0018][0x7034] = ['CS','1','FieldofViewHorizontalFlip'];
        $this->dict[0x0018][0x7040] = ['LT','1','GridAbsorbingMaterial'];
        $this->dict[0x0018][0x7041] = ['LT','1','GridSpacingMaterial'];
        $this->dict[0x0018][0x7042] = ['DS','1','GridThickness'];
        $this->dict[0x0018][0x7044] = ['DS','1','GridPitch'];
        $this->dict[0x0018][0x7046] = ['IS','2','GridAspectRatio'];
        $this->dict[0x0018][0x7048] = ['DS','1','GridPeriod'];
        $this->dict[0x0018][0x704C] = ['DS','1','GridFocalDistance'];
        $this->dict[0x0018][0x7050] = ['LT','1-n','FilterMaterial'];
        $this->dict[0x0018][0x7052] = ['DS','1-n','FilterThicknessMinimum'];
        $this->dict[0x0018][0x7054] = ['DS','1-n','FilterThicknessMaximum'];
        $this->dict[0x0018][0x7060] = ['CS','1','ExposureControlMode'];
        $this->dict[0x0018][0x7062] = ['LT','1','ExposureControlModeDescription'];
        $this->dict[0x0018][0x7064] = ['CS','1','ExposureStatus'];
        $this->dict[0x0018][0x7065] = ['DS','1','PhototimerSetting'];
        $this->dict[0x0020][0x0000] = ['UL','1','ImageGroupLength'];
        $this->dict[0x0020][0x000D] = ['UI','1','StudyInstanceUID'];
        $this->dict[0x0020][0x000E] = ['UI','1','SeriesInstanceUID'];
        $this->dict[0x0020][0x0010] = ['SH','1','StudyID'];
        $this->dict[0x0020][0x0011] = ['IS','1','SeriesNumber'];
        $this->dict[0x0020][0x0012] = ['IS','1','AcquisitionNumber'];
        $this->dict[0x0020][0x0013] = ['IS','1','ImageNumber'];
        $this->dict[0x0020][0x0014] = ['IS','1','IsotopeNumber'];
        $this->dict[0x0020][0x0015] = ['IS','1','PhaseNumber'];
        $this->dict[0x0020][0x0016] = ['IS','1','IntervalNumber'];
        $this->dict[0x0020][0x0017] = ['IS','1','TimeSlotNumber'];
        $this->dict[0x0020][0x0018] = ['IS','1','AngleNumber'];
        $this->dict[0x0020][0x0019] = ['IS','1','ItemNumber'];
        $this->dict[0x0020][0x0020] = ['CS','2','PatientOrientation'];
        $this->dict[0x0020][0x0022] = ['IS','1','OverlayNumber'];
        $this->dict[0x0020][0x0024] = ['IS','1','CurveNumber'];
        $this->dict[0x0020][0x0026] = ['IS','1','LUTNumber'];
        $this->dict[0x0020][0x0030] = ['DS','3','ImagePosition'];
        $this->dict[0x0020][0x0032] = ['DS','3','ImagePositionPatient'];
        $this->dict[0x0020][0x0035] = ['DS','6','ImageOrientation'];
        $this->dict[0x0020][0x0037] = ['DS','6','ImageOrientationPatient'];
        $this->dict[0x0020][0x0050] = ['DS','1','Location'];
        $this->dict[0x0020][0x0052] = ['UI','1','FrameOfReferenceUID'];
        $this->dict[0x0020][0x0060] = ['CS','1','Laterality'];
        $this->dict[0x0020][0x0062] = ['CS','1','ImageLaterality'];
        $this->dict[0x0020][0x0070] = ['LT','1','ImageGeometryType'];
        $this->dict[0x0020][0x0080] = ['CS','1-n','MaskingImage'];
        $this->dict[0x0020][0x0100] = ['IS','1','TemporalPositionIdentifier'];
        $this->dict[0x0020][0x0105] = ['IS','1','NumberOfTemporalPositions'];
        $this->dict[0x0020][0x0110] = ['DS','1','TemporalResolution'];
        $this->dict[0x0020][0x0200] = ['UI','1','SynchronizationFrameofReferenceUID'];
        $this->dict[0x0020][0x1000] = ['IS','1','SeriesInStudy'];
        $this->dict[0x0020][0x1001] = ['IS','1','AcquisitionsInSeries'];
        $this->dict[0x0020][0x1002] = ['IS','1','ImagesInAcquisition'];
        $this->dict[0x0020][0x1003] = ['IS','1','ImagesInSeries'];
        $this->dict[0x0020][0x1004] = ['IS','1','AcquisitionsInStudy'];
        $this->dict[0x0020][0x1005] = ['IS','1','ImagesInStudy'];
        $this->dict[0x0020][0x1020] = ['CS','1-n','Reference'];
        $this->dict[0x0020][0x1040] = ['LO','1','PositionReferenceIndicator'];
        $this->dict[0x0020][0x1041] = ['DS','1','SliceLocation'];
        $this->dict[0x0020][0x1070] = ['IS','1-n','OtherStudyNumbers'];
        $this->dict[0x0020][0x1200] = ['IS','1','NumberOfPatientRelatedStudies'];
        $this->dict[0x0020][0x1202] = ['IS','1','NumberOfPatientRelatedSeries'];
        $this->dict[0x0020][0x1204] = ['IS','1','NumberOfPatientRelatedImages'];
        $this->dict[0x0020][0x1206] = ['IS','1','NumberOfStudyRelatedSeries'];
        $this->dict[0x0020][0x1208] = ['IS','1','NumberOfStudyRelatedImages'];
        $this->dict[0x0020][0x1209] = ['IS','1','NumberOfSeriesRelatedInstances'];
        $this->dict[0x0020][0x3100] = ['CS','1-n','SourceImageID'];
        $this->dict[0x0020][0x3401] = ['CS','1','ModifyingDeviceID'];
        $this->dict[0x0020][0x3402] = ['CS','1','ModifiedImageID'];
        $this->dict[0x0020][0x3403] = ['DA','1','ModifiedImageDate'];
        $this->dict[0x0020][0x3404] = ['LO','1','ModifyingDeviceManufacturer'];
        $this->dict[0x0020][0x3405] = ['TM','1','ModifiedImageTime'];
        $this->dict[0x0020][0x3406] = ['LT','1','ModifiedImageDescription'];
        $this->dict[0x0020][0x4000] = ['LT','1','ImageComments'];
        $this->dict[0x0020][0x5000] = ['AT','1-n','OriginalImageIdentification'];
        $this->dict[0x0020][0x5002] = ['CS','1-n','OriginalImageIdentificationNomenclature'];
        $this->dict[0x0028][0x0000] = ['UL','1','ImagePresentationGroupLength'];
        $this->dict[0x0028][0x0002] = ['US','1','SamplesPerPixel'];
        $this->dict[0x0028][0x0004] = ['CS','1','PhotometricInterpretation'];
        $this->dict[0x0028][0x0005] = ['US','1','ImageDimensions'];
        $this->dict[0x0028][0x0006] = ['US','1','PlanarConfiguration'];
        $this->dict[0x0028][0x0008] = ['IS','1','NumberOfFrames'];
        $this->dict[0x0028][0x0009] = ['AT','1','FrameIncrementPointer'];
        $this->dict[0x0028][0x0010] = ['US','1','Rows'];
        $this->dict[0x0028][0x0011] = ['US','1','Columns'];
        $this->dict[0x0028][0x0012] = ['US','1','Planes'];
        $this->dict[0x0028][0x0014] = ['US','1','UltrasoundColorDataPresent'];
        $this->dict[0x0028][0x0030] = ['DS','2','PixelSpacing'];
        $this->dict[0x0028][0x0031] = ['DS','2','ZoomFactor'];
        $this->dict[0x0028][0x0032] = ['DS','2','ZoomCenter'];
        $this->dict[0x0028][0x0034] = ['IS','2','PixelAspectRatio'];
        $this->dict[0x0028][0x0040] = ['CS','1','ImageFormat'];
        $this->dict[0x0028][0x0050] = ['LT','1-n','ManipulatedImage'];
        $this->dict[0x0028][0x0051] = ['CS','1','CorrectedImage'];
        $this->dict[0x0028][0x005F] = ['CS','1','CompressionRecognitionCode'];
        $this->dict[0x0028][0x0060] = ['CS','1','CompressionCode'];
        $this->dict[0x0028][0x0061] = ['SH','1','CompressionOriginator'];
        $this->dict[0x0028][0x0062] = ['SH','1','CompressionLabel'];
        $this->dict[0x0028][0x0063] = ['SH','1','CompressionDescription'];
        $this->dict[0x0028][0x0065] = ['CS','1-n','CompressionSequence'];
        $this->dict[0x0028][0x0066] = ['AT','1-n','CompressionStepPointers'];
        $this->dict[0x0028][0x0068] = ['US','1','RepeatInterval'];
        $this->dict[0x0028][0x0069] = ['US','1','BitsGrouped'];
        $this->dict[0x0028][0x0070] = ['US','1-n','PerimeterTable'];
        $this->dict[0x0028][0x0071] = ['XS','1','PerimeterValue'];
        $this->dict[0x0028][0x0080] = ['US','1','PredictorRows'];
        $this->dict[0x0028][0x0081] = ['US','1','PredictorColumns'];
        $this->dict[0x0028][0x0082] = ['US','1-n','PredictorConstants'];
        $this->dict[0x0028][0x0090] = ['CS','1','BlockedPixels'];
        $this->dict[0x0028][0x0091] = ['US','1','BlockRows'];
        $this->dict[0x0028][0x0092] = ['US','1','BlockColumns'];
        $this->dict[0x0028][0x0093] = ['US','1','RowOverlap'];
        $this->dict[0x0028][0x0094] = ['US','1','ColumnOverlap'];
        $this->dict[0x0028][0x0100] = ['US','1','BitsAllocated'];
        $this->dict[0x0028][0x0101] = ['US','1','BitsStored'];
        $this->dict[0x0028][0x0102] = ['US','1','HighBit'];
        $this->dict[0x0028][0x0103] = ['US','1','PixelRepresentation'];
        $this->dict[0x0028][0x0104] = ['XS','1','SmallestValidPixelValue'];
        $this->dict[0x0028][0x0105] = ['XS','1','LargestValidPixelValue'];
        $this->dict[0x0028][0x0106] = ['XS','1','SmallestImagePixelValue'];
        $this->dict[0x0028][0x0107] = ['XS','1','LargestImagePixelValue'];
        $this->dict[0x0028][0x0108] = ['XS','1','SmallestPixelValueInSeries'];
        $this->dict[0x0028][0x0109] = ['XS','1','LargestPixelValueInSeries'];
        $this->dict[0x0028][0x0110] = ['XS','1','SmallestPixelValueInPlane'];
        $this->dict[0x0028][0x0111] = ['XS','1','LargestPixelValueInPlane'];
        $this->dict[0x0028][0x0120] = ['XS','1','PixelPaddingValue'];
        $this->dict[0x0028][0x0200] = ['US','1','ImageLocation'];
        $this->dict[0x0028][0x0300] = ['CS','1','QualityControlImage'];
        $this->dict[0x0028][0x0301] = ['CS','1','BurnedInAnnotation'];
        $this->dict[0x0028][0x0400] = ['CS','1','TransformLabel'];
        $this->dict[0x0028][0x0401] = ['CS','1','TransformVersionNumber'];
        $this->dict[0x0028][0x0402] = ['US','1','NumberOfTransformSteps'];
        $this->dict[0x0028][0x0403] = ['CS','1-n','SequenceOfCompressedData'];
        $this->dict[0x0028][0x0404] = ['AT','1-n','DetailsOfCoefficients'];
        $this->dict[0x0028][0x0410] = ['US','1','RowsForNthOrderCoefficients'];
        $this->dict[0x0028][0x0411] = ['US','1','ColumnsForNthOrderCoefficients'];
        $this->dict[0x0028][0x0412] = ['CS','1-n','CoefficientCoding'];
        $this->dict[0x0028][0x0413] = ['AT','1-n','CoefficientCodingPointers'];
        $this->dict[0x0028][0x0700] = ['CS','1','DCTLabel'];
        $this->dict[0x0028][0x0701] = ['CS','1-n','DataBlockDescription'];
        $this->dict[0x0028][0x0702] = ['AT','1-n','DataBlock'];
        $this->dict[0x0028][0x0710] = ['US','1','NormalizationFactorFormat'];
        $this->dict[0x0028][0x0720] = ['US','1','ZonalMapNumberFormat'];
        $this->dict[0x0028][0x0721] = ['AT','1-n','ZonalMapLocation'];
        $this->dict[0x0028][0x0722] = ['US','1','ZonalMapFormat'];
        $this->dict[0x0028][0x0730] = ['US','1','AdaptiveMapFormat'];
        $this->dict[0x0028][0x0740] = ['US','1','CodeNumberFormat'];
        $this->dict[0x0028][0x0800] = ['CS','1-n','CodeLabel'];
        $this->dict[0x0028][0x0802] = ['US','1','NumberOfTables'];
        $this->dict[0x0028][0x0803] = ['AT','1-n','CodeTableLocation'];
        $this->dict[0x0028][0x0804] = ['US','1','BitsForCodeWord'];
        $this->dict[0x0028][0x0808] = ['AT','1-n','ImageDataLocation'];
        $this->dict[0x0028][0x1040] = ['CS','1','PixelIntensityRelationship'];
        $this->dict[0x0028][0x1041] = ['SS','1','PixelIntensityRelationshipSign'];
        $this->dict[0x0028][0x1050] = ['DS','1-n','WindowCenter'];
        $this->dict[0x0028][0x1051] = ['DS','1-n','WindowWidth'];
        $this->dict[0x0028][0x1052] = ['DS','1','RescaleIntercept'];
        $this->dict[0x0028][0x1053] = ['DS','1','RescaleSlope'];
        $this->dict[0x0028][0x1054] = ['LO','1','RescaleType'];
        $this->dict[0x0028][0x1055] = ['LO','1-n','WindowCenterWidthExplanation'];
        $this->dict[0x0028][0x1080] = ['CS','1','GrayScale'];
        $this->dict[0x0028][0x1090] = ['CS','1','RecommendedViewingMode'];
        $this->dict[0x0028][0x1100] = ['XS','3','GrayLookupTableDescriptor'];
        $this->dict[0x0028][0x1101] = ['XS','3','RedPaletteColorLookupTableDescriptor'];
        $this->dict[0x0028][0x1102] = ['XS','3','GreenPaletteColorLookupTableDescriptor'];
        $this->dict[0x0028][0x1103] = ['XS','3','BluePaletteColorLookupTableDescriptor'];
        $this->dict[0x0028][0x1111] = ['US','4','LargeRedPaletteColorLookupTableDescriptor'];
        $this->dict[0x0028][0x1112] = ['US','4','LargeGreenPaletteColorLookupTabe'];
        $this->dict[0x0028][0x1113] = ['US','4','LargeBluePaletteColorLookupTabl'];
        $this->dict[0x0028][0x1199] = ['UI','1','PaletteColorLookupTableUID'];
        $this->dict[0x0028][0x1200] = ['XS','1-n','GrayLookupTableData'];
        $this->dict[0x0028][0x1201] = ['XS','1-n','RedPaletteColorLookupTableData'];
        $this->dict[0x0028][0x1202] = ['XS','1-n','GreenPaletteColorLookupTableData'];
        $this->dict[0x0028][0x1203] = ['XS','1-n','BluePaletteColorLookupTableData'];
        $this->dict[0x0028][0x1211] = ['OW','1','LargeRedPaletteColorLookupTableData'];
        $this->dict[0x0028][0x1212] = ['OW','1','LargeGreenPaletteColorLookupTableData'];
        $this->dict[0x0028][0x1213] = ['OW','1','LargeBluePaletteColorLookupTableData'];
        $this->dict[0x0028][0x1214] = ['UI','1','LargePaletteColorLookupTableUID'];
        $this->dict[0x0028][0x1221] = ['OW','1','SegmentedRedPaletteColorLookupTableData'];
        $this->dict[0x0028][0x1222] = ['OW','1','SegmentedGreenPaletteColorLookupTableData'];
        $this->dict[0x0028][0x1223] = ['OW','1','SegmentedBluePaletteColorLookupTableData'];
        $this->dict[0x0028][0x1300] = ['CS','1','ImplantPresent'];
        $this->dict[0x0028][0x2110] = ['CS','1','LossyImageCompression'];
        $this->dict[0x0028][0x2112] = ['DS','1-n','LossyImageCompressionRatio'];
        $this->dict[0x0028][0x3000] = ['SQ','1','ModalityLUTSequence'];
        $this->dict[0x0028][0x3002] = ['XS','3','LUTDescriptor'];
        $this->dict[0x0028][0x3003] = ['LO','1','LUTExplanation'];
        $this->dict[0x0028][0x3004] = ['LO','1','ModalityLUTType'];
        $this->dict[0x0028][0x3006] = ['XS','1-n','LUTData'];
        $this->dict[0x0028][0x3010] = ['SQ','1','VOILUTSequence'];
        $this->dict[0x0028][0x3110] = ['SQ','1','SoftcopyVOILUTSequence'];
        $this->dict[0x0028][0x4000] = ['LT','1-n','ImagePresentationComments'];
        $this->dict[0x0028][0x5000] = ['SQ','1','BiPlaneAcquisitionSequence'];
        $this->dict[0x0028][0x6010] = ['US','1','RepresentativeFrameNumber'];
        $this->dict[0x0028][0x6020] = ['US','1-n','FrameNumbersOfInterest'];
        $this->dict[0x0028][0x6022] = ['LO','1-n','FrameOfInterestDescription'];
        $this->dict[0x0028][0x6030] = ['US','1-n','MaskPointer'];
        $this->dict[0x0028][0x6040] = ['US','1-n','RWavePointer'];
        $this->dict[0x0028][0x6100] = ['SQ','1','MaskSubtractionSequence'];
        $this->dict[0x0028][0x6101] = ['CS','1','MaskOperation'];
        $this->dict[0x0028][0x6102] = ['US','1-n','ApplicableFrameRange'];
        $this->dict[0x0028][0x6110] = ['US','1-n','MaskFrameNumbers'];
        $this->dict[0x0028][0x6112] = ['US','1','ContrastFrameAveraging'];
        $this->dict[0x0028][0x6114] = ['FL','2','MaskSubPixelShift'];
        $this->dict[0x0028][0x6120] = ['SS','1','TIDOffset'];
        $this->dict[0x0028][0x6190] = ['ST','1','MaskOperationExplanation'];
        $this->dict[0x0032][0x0000] = ['UL','1','StudyGroupLength'];
        $this->dict[0x0032][0x000A] = ['CS','1','StudyStatusID'];
        $this->dict[0x0032][0x000C] = ['CS','1','StudyPriorityID'];
        $this->dict[0x0032][0x0012] = ['LO','1','StudyIDIssuer'];
        $this->dict[0x0032][0x0032] = ['DA','1','StudyVerifiedDate'];
        $this->dict[0x0032][0x0033] = ['TM','1','StudyVerifiedTime'];
        $this->dict[0x0032][0x0034] = ['DA','1','StudyReadDate'];
        $this->dict[0x0032][0x0035] = ['TM','1','StudyReadTime'];
        $this->dict[0x0032][0x1000] = ['DA','1','ScheduledStudyStartDate'];
        $this->dict[0x0032][0x1001] = ['TM','1','ScheduledStudyStartTime'];
        $this->dict[0x0032][0x1010] = ['DA','1','ScheduledStudyStopDate'];
        $this->dict[0x0032][0x1011] = ['TM','1','ScheduledStudyStopTime'];
        $this->dict[0x0032][0x1020] = ['LO','1','ScheduledStudyLocation'];
        $this->dict[0x0032][0x1021] = ['AE','1-n','ScheduledStudyLocationAETitle'];
        $this->dict[0x0032][0x1030] = ['LO','1','ReasonForStudy'];
        $this->dict[0x0032][0x1032] = ['PN','1','RequestingPhysician'];
        $this->dict[0x0032][0x1033] = ['LO','1','RequestingService'];
        $this->dict[0x0032][0x1040] = ['DA','1','StudyArrivalDate'];
        $this->dict[0x0032][0x1041] = ['TM','1','StudyArrivalTime'];
        $this->dict[0x0032][0x1050] = ['DA','1','StudyCompletionDate'];
        $this->dict[0x0032][0x1051] = ['TM','1','StudyCompletionTime'];
        $this->dict[0x0032][0x1055] = ['CS','1','StudyComponentStatusID'];
        $this->dict[0x0032][0x1060] = ['LO','1','RequestedProcedureDescription'];
        $this->dict[0x0032][0x1064] = ['SQ','1','RequestedProcedureCodeSequence'];
        $this->dict[0x0032][0x1070] = ['LO','1','RequestedContrastAgent'];
        $this->dict[0x0032][0x4000] = ['LT','1','StudyComments'];
        $this->dict[0x0038][0x0000] = ['UL','1','VisitGroupLength'];
        $this->dict[0x0038][0x0004] = ['SQ','1','ReferencedPatientAliasSequence'];
        $this->dict[0x0038][0x0008] = ['CS','1','VisitStatusID'];
        $this->dict[0x0038][0x0010] = ['LO','1','AdmissionID'];
        $this->dict[0x0038][0x0011] = ['LO','1','IssuerOfAdmissionID'];
        $this->dict[0x0038][0x0016] = ['LO','1','RouteOfAdmissions'];
        $this->dict[0x0038][0x001A] = ['DA','1','ScheduledAdmissionDate'];
        $this->dict[0x0038][0x001B] = ['TM','1','ScheduledAdmissionTime'];
        $this->dict[0x0038][0x001C] = ['DA','1','ScheduledDischargeDate'];
        $this->dict[0x0038][0x001D] = ['TM','1','ScheduledDischargeTime'];
        $this->dict[0x0038][0x001E] = ['LO','1','ScheduledPatientInstitutionResidence'];
        $this->dict[0x0038][0x0020] = ['DA','1','AdmittingDate'];
        $this->dict[0x0038][0x0021] = ['TM','1','AdmittingTime'];
        $this->dict[0x0038][0x0030] = ['DA','1','DischargeDate'];
        $this->dict[0x0038][0x0032] = ['TM','1','DischargeTime'];
        $this->dict[0x0038][0x0040] = ['LO','1','DischargeDiagnosisDescription'];
        $this->dict[0x0038][0x0044] = ['SQ','1','DischargeDiagnosisCodeSequence'];
        $this->dict[0x0038][0x0050] = ['LO','1','SpecialNeeds'];
        $this->dict[0x0038][0x0300] = ['LO','1','CurrentPatientLocation'];
        $this->dict[0x0038][0x0400] = ['LO','1','PatientInstitutionResidence'];
        $this->dict[0x0038][0x0500] = ['LO','1','PatientState'];
        $this->dict[0x0038][0x4000] = ['LT','1','VisitComments'];
        $this->dict[0x003A][0x0004] = ['CS','1','WaveformOriginality'];
        $this->dict[0x003A][0x0005] = ['US','1','NumberofChannels'];
        $this->dict[0x003A][0x0010] = ['UL','1','NumberofSamples'];
        $this->dict[0x003A][0x001A] = ['DS','1','SamplingFrequency'];
        $this->dict[0x003A][0x0020] = ['SH','1','MultiplexGroupLabel'];
        $this->dict[0x003A][0x0200] = ['SQ','1','ChannelDefinitionSequence'];
        $this->dict[0x003A][0x0202] = ['IS','1','WVChannelNumber'];
        $this->dict[0x003A][0x0203] = ['SH','1','ChannelLabel'];
        $this->dict[0x003A][0x0205] = ['CS','1-n','ChannelStatus'];
        $this->dict[0x003A][0x0208] = ['SQ','1','ChannelSourceSequence'];
        $this->dict[0x003A][0x0209] = ['SQ','1','ChannelSourceModifiersSequence'];
        $this->dict[0x003A][0x020A] = ['SQ','1','SourceWaveformSequence'];
        $this->dict[0x003A][0x020C] = ['LO','1','ChannelDerivationDescription'];
        $this->dict[0x003A][0x0210] = ['DS','1','ChannelSensitivity'];
        $this->dict[0x003A][0x0211] = ['SQ','1','ChannelSensitivityUnits'];
        $this->dict[0x003A][0x0212] = ['DS','1','ChannelSensitivityCorrectionFactor'];
        $this->dict[0x003A][0x0213] = ['DS','1','ChannelBaseline'];
        $this->dict[0x003A][0x0214] = ['DS','1','ChannelTimeSkew'];
        $this->dict[0x003A][0x0215] = ['DS','1','ChannelSampleSkew'];
        $this->dict[0x003A][0x0218] = ['DS','1','ChannelOffset'];
        $this->dict[0x003A][0x021A] = ['US','1','WaveformBitsStored'];
        $this->dict[0x003A][0x0220] = ['DS','1','FilterLowFrequency'];
        $this->dict[0x003A][0x0221] = ['DS','1','FilterHighFrequency'];
        $this->dict[0x003A][0x0222] = ['DS','1','NotchFilterFrequency'];
        $this->dict[0x003A][0x0223] = ['DS','1','NotchFilterBandwidth'];
        $this->dict[0x0040][0x0000] = ['UL','1','ModalityWorklistGroupLength'];
        $this->dict[0x0040][0x0001] = ['AE','1','ScheduledStationAETitle'];
        $this->dict[0x0040][0x0002] = ['DA','1','ScheduledProcedureStepStartDate'];
        $this->dict[0x0040][0x0003] = ['TM','1','ScheduledProcedureStepStartTime'];
        $this->dict[0x0040][0x0004] = ['DA','1','ScheduledProcedureStepEndDate'];
        $this->dict[0x0040][0x0005] = ['TM','1','ScheduledProcedureStepEndTime'];
        $this->dict[0x0040][0x0006] = ['PN','1','ScheduledPerformingPhysicianName'];
        $this->dict[0x0040][0x0007] = ['LO','1','ScheduledProcedureStepDescription'];
        $this->dict[0x0040][0x0008] = ['SQ','1','ScheduledProcedureStepCodeSequence'];
        $this->dict[0x0040][0x0009] = ['SH','1','ScheduledProcedureStepID'];
        $this->dict[0x0040][0x0010] = ['SH','1','ScheduledStationName'];
        $this->dict[0x0040][0x0011] = ['SH','1','ScheduledProcedureStepLocation'];
        $this->dict[0x0040][0x0012] = ['LO','1','ScheduledPreOrderOfMedication'];
        $this->dict[0x0040][0x0020] = ['CS','1','ScheduledProcedureStepStatus'];
        $this->dict[0x0040][0x0100] = ['SQ','1-n','ScheduledProcedureStepSequence'];
        $this->dict[0x0040][0x0220] = ['SQ','1','ReferencedStandaloneSOPInstanceSequence'];
        $this->dict[0x0040][0x0241] = ['AE','1','PerformedStationAETitle'];
        $this->dict[0x0040][0x0242] = ['SH','1','PerformedStationName'];
        $this->dict[0x0040][0x0243] = ['SH','1','PerformedLocation'];
        $this->dict[0x0040][0x0244] = ['DA','1','PerformedProcedureStepStartDate'];
        $this->dict[0x0040][0x0245] = ['TM','1','PerformedProcedureStepStartTime'];
        $this->dict[0x0040][0x0250] = ['DA','1','PerformedProcedureStepEndDate'];
        $this->dict[0x0040][0x0251] = ['TM','1','PerformedProcedureStepEndTime'];
        $this->dict[0x0040][0x0252] = ['CS','1','PerformedProcedureStepStatus'];
        $this->dict[0x0040][0x0253] = ['CS','1','PerformedProcedureStepID'];
        $this->dict[0x0040][0x0254] = ['LO','1','PerformedProcedureStepDescription'];
        $this->dict[0x0040][0x0255] = ['LO','1','PerformedProcedureTypeDescription'];
        $this->dict[0x0040][0x0260] = ['SQ','1','PerformedActionItemSequence'];
        $this->dict[0x0040][0x0270] = ['SQ','1','ScheduledStepAttributesSequence'];
        $this->dict[0x0040][0x0275] = ['SQ','1','RequestAttributesSequence'];
        $this->dict[0x0040][0x0280] = ['ST','1','CommentsOnThePerformedProcedureSteps'];
        $this->dict[0x0040][0x0293] = ['SQ','1','QuantitySequence'];
        $this->dict[0x0040][0x0294] = ['DS','1','Quantity'];
        $this->dict[0x0040][0x0295] = ['SQ','1','MeasuringUnitsSequence'];
        $this->dict[0x0040][0x0296] = ['SQ','1','BillingItemSequence'];
        $this->dict[0x0040][0x0300] = ['US','1','TotalTimeOfFluoroscopy'];
        $this->dict[0x0040][0x0301] = ['US','1','TotalNumberOfExposures'];
        $this->dict[0x0040][0x0302] = ['US','1','EntranceDose'];
        $this->dict[0x0040][0x0303] = ['US','1-2','ExposedArea'];
        $this->dict[0x0040][0x0306] = ['DS','1','DistanceSourceToEntrance'];
        $this->dict[0x0040][0x0307] = ['DS','1','DistanceSourceToSupport'];
        $this->dict[0x0040][0x0310] = ['ST','1','CommentsOnRadiationDose'];
        $this->dict[0x0040][0x0312] = ['DS','1','XRayOutput'];
        $this->dict[0x0040][0x0314] = ['DS','1','HalfValueLayer'];
        $this->dict[0x0040][0x0316] = ['DS','1','OrganDose'];
        $this->dict[0x0040][0x0318] = ['CS','1','OrganExposed'];
        $this->dict[0x0040][0x0320] = ['SQ','1','BillingProcedureStepSequence'];
        $this->dict[0x0040][0x0321] = ['SQ','1','FilmConsumptionSequence'];
        $this->dict[0x0040][0x0324] = ['SQ','1','BillingSuppliesAndDevicesSequence'];
        $this->dict[0x0040][0x0330] = ['SQ','1','ReferencedProcedureStepSequence'];
        $this->dict[0x0040][0x0340] = ['SQ','1','PerformedSeriesSequence'];
        $this->dict[0x0040][0x0400] = ['LT','1','CommentsOnScheduledProcedureStep'];
        $this->dict[0x0040][0x050A] = ['LO','1','SpecimenAccessionNumber'];
        $this->dict[0x0040][0x0550] = ['SQ','1','SpecimenSequence'];
        $this->dict[0x0040][0x0551] = ['LO','1','SpecimenIdentifier'];
        $this->dict[0x0040][0x0555] = ['SQ','1','AcquisitionContextSequence'];
        $this->dict[0x0040][0x0556] = ['ST','1','AcquisitionContextDescription'];
        $this->dict[0x0040][0x059A] = ['SQ','1','SpecimenTypeCodeSequence'];
        $this->dict[0x0040][0x06FA] = ['LO','1','SlideIdentifier'];
        $this->dict[0x0040][0x071A] = ['SQ','1','ImageCenterPointCoordinatesSequence'];
        $this->dict[0x0040][0x072A] = ['DS','1','XOffsetInSlideCoordinateSystem'];
        $this->dict[0x0040][0x073A] = ['DS','1','YOffsetInSlideCoordinateSystem'];
        $this->dict[0x0040][0x074A] = ['DS','1','ZOffsetInSlideCoordinateSystem'];
        $this->dict[0x0040][0x08D8] = ['SQ','1','PixelSpacingSequence'];
        $this->dict[0x0040][0x08DA] = ['SQ','1','CoordinateSystemAxisCodeSequence'];
        $this->dict[0x0040][0x08EA] = ['SQ','1','MeasurementUnitsCodeSequence'];
        $this->dict[0x0040][0x1001] = ['SH','1','RequestedProcedureID'];
        $this->dict[0x0040][0x1002] = ['LO','1','ReasonForRequestedProcedure'];
        $this->dict[0x0040][0x1003] = ['SH','1','RequestedProcedurePriority'];
        $this->dict[0x0040][0x1004] = ['LO','1','PatientTransportArrangements'];
        $this->dict[0x0040][0x1005] = ['LO','1','RequestedProcedureLocation'];
        $this->dict[0x0040][0x1006] = ['SH','1','PlacerOrderNumberOfProcedure'];
        $this->dict[0x0040][0x1007] = ['SH','1','FillerOrderNumberOfProcedure'];
        $this->dict[0x0040][0x1008] = ['LO','1','ConfidentialityCode'];
        $this->dict[0x0040][0x1009] = ['SH','1','ReportingPriority'];
        $this->dict[0x0040][0x1010] = ['PN','1-n','NamesOfIntendedRecipientsOfResults'];
        $this->dict[0x0040][0x1400] = ['LT','1','RequestedProcedureComments'];
        $this->dict[0x0040][0x2001] = ['LO','1','ReasonForTheImagingServiceRequest'];
        $this->dict[0x0040][0x2002] = ['LO','1','ImagingServiceRequestDescription'];
        $this->dict[0x0040][0x2004] = ['DA','1','IssueDateOfImagingServiceRequest'];
        $this->dict[0x0040][0x2005] = ['TM','1','IssueTimeOfImagingServiceRequest'];
        $this->dict[0x0040][0x2006] = ['SH','1','PlacerOrderNumberOfImagingServiceRequest'];
        $this->dict[0x0040][0x2007] = ['SH','0','FillerOrderNumberOfImagingServiceRequest'];
        $this->dict[0x0040][0x2008] = ['PN','1','OrderEnteredBy'];
        $this->dict[0x0040][0x2009] = ['SH','1','OrderEntererLocation'];
        $this->dict[0x0040][0x2010] = ['SH','1','OrderCallbackPhoneNumber'];
        $this->dict[0x0040][0x2016] = ['LO','1','PlacerOrderNumberImagingServiceRequest'];
        $this->dict[0x0040][0x2017] = ['LO','1','FillerOrderNumberImagingServiceRequest'];
        $this->dict[0x0040][0x2400] = ['LT','1','ImagingServiceRequestComments'];
        $this->dict[0x0040][0x3001] = ['LT','1','ConfidentialityConstraint'];
        $this->dict[0x0040][0xA010] = ['CS','1','RelationshipType'];
        $this->dict[0x0040][0xA027] = ['LO','1','VerifyingOrganization'];
        $this->dict[0x0040][0xA030] = ['DT','1','VerificationDateTime'];
        $this->dict[0x0040][0xA032] = ['DT','1','ObservationDateTime'];
        $this->dict[0x0040][0xA040] = ['CS','1','ValueType'];
        $this->dict[0x0040][0xA043] = ['SQ','1','ConceptNameCodeSequence'];
        $this->dict[0x0040][0xA050] = ['CS','1','ContinuityOfContent'];
        $this->dict[0x0040][0xA073] = ['SQ','1','VerifyingObserverSequence'];
        $this->dict[0x0040][0xA075] = ['PN','1','VerifyingObserverName'];
        $this->dict[0x0040][0xA088] = ['SQ','1','VerifyingObserverIdentificationCodeSeque'];
        $this->dict[0x0040][0xA0B0] = ['US','2-2n','ReferencedWaveformChannels'];
        $this->dict[0x0040][0xA120] = ['DT','1','DateTime'];
        $this->dict[0x0040][0xA121] = ['DA','1','Date'];
        $this->dict[0x0040][0xA122] = ['TM','1','Time'];
        $this->dict[0x0040][0xA123] = ['PN','1','PersonName'];
        $this->dict[0x0040][0xA124] = ['UI','1','UID'];
        $this->dict[0x0040][0xA130] = ['CS','1','TemporalRangeType'];
        $this->dict[0x0040][0xA132] = ['UL','1-n','ReferencedSamplePositionsU'];
        $this->dict[0x0040][0xA136] = ['US','1-n','ReferencedFrameNumbers'];
        $this->dict[0x0040][0xA138] = ['DS','1-n','ReferencedTimeOffsets'];
        $this->dict[0x0040][0xA13A] = ['DT','1-n','ReferencedDatetime'];
        $this->dict[0x0040][0xA160] = ['UT','1','TextValue'];
        $this->dict[0x0040][0xA168] = ['SQ','1','ConceptCodeSequence'];
        $this->dict[0x0040][0xA180] = ['US','1','AnnotationGroupNumber'];
        $this->dict[0x0040][0xA195] = ['SQ','1','ConceptNameCodeSequenceModifier'];
        $this->dict[0x0040][0xA300] = ['SQ','1','MeasuredValueSequence'];
        $this->dict[0x0040][0xA30A] = ['DS','1-n','NumericValue'];
        $this->dict[0x0040][0xA360] = ['SQ','1','PredecessorDocumentsSequence'];
        $this->dict[0x0040][0xA370] = ['SQ','1','ReferencedRequestSequence'];
        $this->dict[0x0040][0xA372] = ['SQ','1','PerformedProcedureCodeSequence'];
        $this->dict[0x0040][0xA375] = ['SQ','1','CurrentRequestedProcedureEvidenceSequenSequence'];
        $this->dict[0x0040][0xA385] = ['SQ','1','PertinentOtherEvidenceSequence'];
        $this->dict[0x0040][0xA491] = ['CS','1','CompletionFlag'];
        $this->dict[0x0040][0xA492] = ['LO','1','CompletionFlagDescription'];
        $this->dict[0x0040][0xA493] = ['CS','1','VerificationFlag'];
        $this->dict[0x0040][0xA504] = ['SQ','1','ContentTemplateSequence'];
        $this->dict[0x0040][0xA525] = ['SQ','1','IdenticalDocumentsSequence'];
        $this->dict[0x0040][0xA730] = ['SQ','1','ContentSequence'];
        $this->dict[0x0040][0xB020] = ['SQ','1','AnnotationSequence'];
        $this->dict[0x0040][0xDB00] = ['CS','1','TemplateIdentifier'];
        $this->dict[0x0040][0xDB06] = ['DT','1','TemplateVersion'];
        $this->dict[0x0040][0xDB07] = ['DT','1','TemplateLocalVersion'];
        $this->dict[0x0040][0xDB0B] = ['CS','1','TemplateExtensionFlag'];
        $this->dict[0x0040][0xDB0C] = ['UI','1','TemplateExtensionOrganizationUID'];
        $this->dict[0x0040][0xDB0D] = ['UI','1','TemplateExtensionCreatorUID'];
        $this->dict[0x0040][0xDB73] = ['UL','1-n','ReferencedContentItemIdentifier'];
        $this->dict[0x0050][0x0000] = ['UL','1','XRayAngioDeviceGroupLength'];
        $this->dict[0x0050][0x0004] = ['CS','1','CalibrationObject'];
        $this->dict[0x0050][0x0010] = ['SQ','1','DeviceSequence'];
        $this->dict[0x0050][0x0012] = ['CS','1','DeviceType'];
        $this->dict[0x0050][0x0014] = ['DS','1','DeviceLength'];
        $this->dict[0x0050][0x0016] = ['DS','1','DeviceDiameter'];
        $this->dict[0x0050][0x0017] = ['CS','1','DeviceDiameterUnits'];
        $this->dict[0x0050][0x0018] = ['DS','1','DeviceVolume'];
        $this->dict[0x0050][0x0019] = ['DS','1','InterMarkerDistance'];
        $this->dict[0x0050][0x0020] = ['LO','1','DeviceDescription'];
        $this->dict[0x0050][0x0030] = ['SQ','1','CodedInterventionalDeviceSequence'];
        $this->dict[0x0054][0x0000] = ['UL','1','NuclearMedicineGroupLength'];
        $this->dict[0x0054][0x0010] = ['US','1-n','EnergyWindowVector'];
        $this->dict[0x0054][0x0011] = ['US','1','NumberOfEnergyWindows'];
        $this->dict[0x0054][0x0012] = ['SQ','1','EnergyWindowInformationSequence'];
        $this->dict[0x0054][0x0013] = ['SQ','1','EnergyWindowRangeSequence'];
        $this->dict[0x0054][0x0014] = ['DS','1','EnergyWindowLowerLimit'];
        $this->dict[0x0054][0x0015] = ['DS','1','EnergyWindowUpperLimit'];
        $this->dict[0x0054][0x0016] = ['SQ','1','RadiopharmaceuticalInformationSequence'];
        $this->dict[0x0054][0x0017] = ['IS','1','ResidualSyringeCounts'];
        $this->dict[0x0054][0x0018] = ['SH','1','EnergyWindowName'];
        $this->dict[0x0054][0x0020] = ['US','1-n','DetectorVector'];
        $this->dict[0x0054][0x0021] = ['US','1','NumberOfDetectors'];
        $this->dict[0x0054][0x0022] = ['SQ','1','DetectorInformationSequence'];
        $this->dict[0x0054][0x0030] = ['US','1-n','PhaseVector'];
        $this->dict[0x0054][0x0031] = ['US','1','NumberOfPhases'];
        $this->dict[0x0054][0x0032] = ['SQ','1','PhaseInformationSequence'];
        $this->dict[0x0054][0x0033] = ['US','1','NumberOfFramesInPhase'];
        $this->dict[0x0054][0x0036] = ['IS','1','PhaseDelay'];
        $this->dict[0x0054][0x0038] = ['IS','1','PauseBetweenFrames'];
        $this->dict[0x0054][0x0050] = ['US','1-n','RotationVector'];
        $this->dict[0x0054][0x0051] = ['US','1','NumberOfRotations'];
        $this->dict[0x0054][0x0052] = ['SQ','1','RotationInformationSequence'];
        $this->dict[0x0054][0x0053] = ['US','1','NumberOfFramesInRotation'];
        $this->dict[0x0054][0x0060] = ['US','1-n','RRIntervalVector'];
        $this->dict[0x0054][0x0061] = ['US','1','NumberOfRRIntervals'];
        $this->dict[0x0054][0x0062] = ['SQ','1','GatedInformationSequence'];
        $this->dict[0x0054][0x0063] = ['SQ','1','DataInformationSequence'];
        $this->dict[0x0054][0x0070] = ['US','1-n','TimeSlotVector'];
        $this->dict[0x0054][0x0071] = ['US','1','NumberOfTimeSlots'];
        $this->dict[0x0054][0x0072] = ['SQ','1','TimeSlotInformationSequence'];
        $this->dict[0x0054][0x0073] = ['DS','1','TimeSlotTime'];
        $this->dict[0x0054][0x0080] = ['US','1-n','SliceVector'];
        $this->dict[0x0054][0x0081] = ['US','1','NumberOfSlices'];
        $this->dict[0x0054][0x0090] = ['US','1-n','AngularViewVector'];
        $this->dict[0x0054][0x0100] = ['US','1-n','TimeSliceVector'];
        $this->dict[0x0054][0x0101] = ['US','1','NumberOfTimeSlices'];
        $this->dict[0x0054][0x0200] = ['DS','1','StartAngle'];
        $this->dict[0x0054][0x0202] = ['CS','1','TypeOfDetectorMotion'];
        $this->dict[0x0054][0x0210] = ['IS','1-n','TriggerVector'];
        $this->dict[0x0054][0x0211] = ['US','1','NumberOfTriggersInPhase'];
        $this->dict[0x0054][0x0220] = ['SQ','1','ViewCodeSequence'];
        $this->dict[0x0054][0x0222] = ['SQ','1','ViewAngulationModifierCodeSequence'];
        $this->dict[0x0054][0x0300] = ['SQ','1','RadionuclideCodeSequence'];
        $this->dict[0x0054][0x0302] = ['SQ','1','AdministrationRouteCodeSequence'];
        $this->dict[0x0054][0x0304] = ['SQ','1','RadiopharmaceuticalCodeSequence'];
        $this->dict[0x0054][0x0306] = ['SQ','1','CalibrationDataSequence'];
        $this->dict[0x0054][0x0308] = ['US','1','EnergyWindowNumber'];
        $this->dict[0x0054][0x0400] = ['SH','1','ImageID'];
        $this->dict[0x0054][0x0410] = ['SQ','1','PatientOrientationCodeSequence'];
        $this->dict[0x0054][0x0412] = ['SQ','1','PatientOrientationModifierCodeSequence'];
        $this->dict[0x0054][0x0414] = ['SQ','1','PatientGantryRelationshipCodeSequence'];
        $this->dict[0x0054][0x1000] = ['CS','2','SeriesType'];
        $this->dict[0x0054][0x1001] = ['CS','1','Units'];
        $this->dict[0x0054][0x1002] = ['CS','1','CountsSource'];
        $this->dict[0x0054][0x1004] = ['CS','1','ReprojectionMethod'];
        $this->dict[0x0054][0x1100] = ['CS','1','RandomsCorrectionMethod'];
        $this->dict[0x0054][0x1101] = ['LO','1','AttenuationCorrectionMethod'];
        $this->dict[0x0054][0x1102] = ['CS','1','DecayCorrection'];
        $this->dict[0x0054][0x1103] = ['LO','1','ReconstructionMethod'];
        $this->dict[0x0054][0x1104] = ['LO','1','DetectorLinesOfResponseUsed'];
        $this->dict[0x0054][0x1105] = ['LO','1','ScatterCorrectionMethod'];
        $this->dict[0x0054][0x1200] = ['DS','1','AxialAcceptance'];
        $this->dict[0x0054][0x1201] = ['IS','2','AxialMash'];
        $this->dict[0x0054][0x1202] = ['IS','1','TransverseMash'];
        $this->dict[0x0054][0x1203] = ['DS','2','DetectorElementSize'];
        $this->dict[0x0054][0x1210] = ['DS','1','CoincidenceWindowWidth'];
        $this->dict[0x0054][0x1220] = ['CS','1-n','SecondaryCountsType'];
        $this->dict[0x0054][0x1300] = ['DS','1','FrameReferenceTime'];
        $this->dict[0x0054][0x1310] = ['IS','1','PrimaryPromptsCountsAccumulated'];
        $this->dict[0x0054][0x1311] = ['IS','1-n','SecondaryCountsAccumulated'];
        $this->dict[0x0054][0x1320] = ['DS','1','SliceSensitivityFactor'];
        $this->dict[0x0054][0x1321] = ['DS','1','DecayFactor'];
        $this->dict[0x0054][0x1322] = ['DS','1','DoseCalibrationFactor'];
        $this->dict[0x0054][0x1323] = ['DS','1','ScatterFractionFactor'];
        $this->dict[0x0054][0x1324] = ['DS','1','DeadTimeFactor'];
        $this->dict[0x0054][0x1330] = ['US','1','ImageIndex'];
        $this->dict[0x0054][0x1400] = ['CS','1-n','CountsIncluded'];
        $this->dict[0x0054][0x1401] = ['CS','1','DeadTimeCorrectionFlag'];
        $this->dict[0x0060][0x0000] = ['UL','1','HistogramGroupLength'];
        $this->dict[0x0060][0x3000] = ['SQ','1','HistogramSequence'];
        $this->dict[0x0060][0x3002] = ['US','1','HistogramNumberofBins'];
        $this->dict[0x0060][0x3004] = ['US/SS','1','HistogramFirstBinValue'];
        $this->dict[0x0060][0x3006] = ['US/SS','1','HistogramLastBinValue'];
        $this->dict[0x0060][0x3008] = ['US','1','HistogramBinWidth'];
        $this->dict[0x0060][0x3010] = ['LO','1','HistogramExplanation'];
        $this->dict[0x0060][0x3020] = ['UL','1-n','HistogramData'];
        $this->dict[0x0070][0x0001] = ['SQ','1','GraphicAnnotationSequence'];
        $this->dict[0x0070][0x0002] = ['CS','1','GraphicLayer'];
        $this->dict[0x0070][0x0003] = ['CS','1','BoundingBoxAnnotationUnits'];
        $this->dict[0x0070][0x0004] = ['CS','1','AnchorPointAnnotationUnits'];
        $this->dict[0x0070][0x0005] = ['CS','1','GraphicAnnotationUnits'];
        $this->dict[0x0070][0x0006] = ['ST','1','UnformattedTextValue'];
        $this->dict[0x0070][0x0008] = ['SQ','1','TextObjectSequence'];
        $this->dict[0x0070][0x0009] = ['SQ','1','GraphicObjectSequence'];
        $this->dict[0x0070][0x0010] = ['FL','2','BoundingBoxTopLeftHandCorner'];
        $this->dict[0x0070][0x0011] = ['FL','2','BoundingBoxBottomRightHandCorner'];
        $this->dict[0x0070][0x0012] = ['CS','1','BoundingBoxTextHorizontalJustification'];
        $this->dict[0x0070][0x0014] = ['FL','2','AnchorPoint'];
        $this->dict[0x0070][0x0015] = ['CS','1','AnchorPointVisibility'];
        $this->dict[0x0070][0x0020] = ['US','1','GraphicDimensions'];
        $this->dict[0x0070][0x0021] = ['US','1','NumberOfGraphicPoints'];
        $this->dict[0x0070][0x0022] = ['FL','2-n','GraphicData'];
        $this->dict[0x0070][0x0023] = ['CS','1','GraphicType'];
        $this->dict[0x0070][0x0024] = ['CS','1','GraphicFilled'];
        $this->dict[0x0070][0x0040] = ['IS','1','ImageRotationFrozenDraftRetired'];
        $this->dict[0x0070][0x0041] = ['CS','1','ImageHorizontalFlip'];
        $this->dict[0x0070][0x0042] = ['US','1','ImageRotation'];
        $this->dict[0x0070][0x0050] = ['US','2','DisplayedAreaTLHCFrozenDraftRetired'];
        $this->dict[0x0070][0x0051] = ['US','2','DisplayedAreaBRHCFrozenDraftRetired'];
        $this->dict[0x0070][0x0052] = ['SL','2','DisplayedAreaTopLeftHandCorner'];
        $this->dict[0x0070][0x0053] = ['SL','2','DisplayedAreaBottomRightHandCorner'];
        $this->dict[0x0070][0x005A] = ['SQ','1','DisplayedAreaSelectionSequence'];
        $this->dict[0x0070][0x0060] = ['SQ','1','GraphicLayerSequence'];
        $this->dict[0x0070][0x0062] = ['IS','1','GraphicLayerOrder'];
        $this->dict[0x0070][0x0066] = ['US','1','GraphicLayerRecommendedDisplayGrayscaleValue'];
        $this->dict[0x0070][0x0067] = ['US','3','GraphicLayerRecommendedDisplayRGBValue'];
        $this->dict[0x0070][0x0068] = ['LO','1','GraphicLayerDescription'];
        $this->dict[0x0070][0x0080] = ['CS','1','PresentationLabel'];
        $this->dict[0x0070][0x0081] = ['LO','1','PresentationDescription'];
        $this->dict[0x0070][0x0082] = ['DA','1','PresentationCreationDate'];
        $this->dict[0x0070][0x0083] = ['TM','1','PresentationCreationTime'];
        $this->dict[0x0070][0x0084] = ['PN','1','PresentationCreatorsName'];
        $this->dict[0x0070][0x0100] = ['CS','1','PresentationSizeMode'];
        $this->dict[0x0070][0x0101] = ['DS','2','PresentationPixelSpacing'];
        $this->dict[0x0070][0x0102] = ['IS','2','PresentationPixelAspectRatio'];
        $this->dict[0x0070][0x0103] = ['FL','1','PresentationPixelMagnificationRatio'];
        $this->dict[0x0088][0x0000] = ['UL','1','StorageGroupLength'];
        $this->dict[0x0088][0x0130] = ['SH','1','StorageMediaFilesetID'];
        $this->dict[0x0088][0x0140] = ['UI','1','StorageMediaFilesetUID'];
        $this->dict[0x0088][0x0200] = ['SQ','1','IconImage'];
        $this->dict[0x0088][0x0904] = ['LO','1','TopicTitle'];
        $this->dict[0x0088][0x0906] = ['ST','1','TopicSubject'];
        $this->dict[0x0088][0x0910] = ['LO','1','TopicAuthor'];
        $this->dict[0x0088][0x0912] = ['LO','3','TopicKeyWords'];
        $this->dict[0x1000][0x0000] = ['UL','1','CodeTableGroupLength'];
        $this->dict[0x1000][0x0010] = ['US','3','EscapeTriplet'];
        $this->dict[0x1000][0x0011] = ['US','3','RunLengthTriplet'];
        $this->dict[0x1000][0x0012] = ['US','1','HuffmanTableSize'];
        $this->dict[0x1000][0x0013] = ['US','3','HuffmanTableTriplet'];
        $this->dict[0x1000][0x0014] = ['US','1','ShiftTableSize'];
        $this->dict[0x1000][0x0015] = ['US','3','ShiftTableTriplet'];
        $this->dict[0x1010][0x0000] = ['UL','1','ZonalMapGroupLength'];
        $this->dict[0x1010][0x0004] = ['US','1-n','ZonalMap'];
        $this->dict[0x2000][0x0000] = ['UL','1','FilmSessionGroupLength'];
        $this->dict[0x2000][0x0010] = ['IS','1','NumberOfCopies'];
        $this->dict[0x2000][0x001E] = ['SQ','1','PrinterConfigurationSequence'];
        $this->dict[0x2000][0x0020] = ['CS','1','PrintPriority'];
        $this->dict[0x2000][0x0030] = ['CS','1','MediumType'];
        $this->dict[0x2000][0x0040] = ['CS','1','FilmDestination'];
        $this->dict[0x2000][0x0050] = ['LO','1','FilmSessionLabel'];
        $this->dict[0x2000][0x0060] = ['IS','1','MemoryAllocation'];
        $this->dict[0x2000][0x0061] = ['IS','1','MaximumMemoryAllocation'];
        $this->dict[0x2000][0x0062] = ['CS','1','ColorImagePrintingFlag'];
        $this->dict[0x2000][0x0063] = ['CS','1','CollationFlag'];
        $this->dict[0x2000][0x0065] = ['CS','1','AnnotationFlag'];
        $this->dict[0x2000][0x0067] = ['CS','1','ImageOverlayFlag'];
        $this->dict[0x2000][0x0069] = ['CS','1','PresentationLUTFlag'];
        $this->dict[0x2000][0x006A] = ['CS','1','ImageBoxPresentationLUTFlag'];
        $this->dict[0x2000][0x00A0] = ['US','1','MemoryBitDepth'];
        $this->dict[0x2000][0x00A1] = ['US','1','PrintingBitDepth'];
        $this->dict[0x2000][0x00A2] = ['SQ','1','MediaInstalledSequence'];
        $this->dict[0x2000][0x00A4] = ['SQ','1','OtherMediaAvailableSequence'];
        $this->dict[0x2000][0x00A8] = ['SQ','1','SupportedImageDisplayFormatsSequence'];
        $this->dict[0x2000][0x0500] = ['SQ','1','ReferencedFilmBoxSequence'];
        $this->dict[0x2000][0x0510] = ['SQ','1','ReferencedStoredPrintSequence'];
        $this->dict[0x2010][0x0000] = ['UL','1','FilmBoxGroupLength'];
        $this->dict[0x2010][0x0010] = ['ST','1','ImageDisplayFormat'];
        $this->dict[0x2010][0x0030] = ['CS','1','AnnotationDisplayFormatID'];
        $this->dict[0x2010][0x0040] = ['CS','1','FilmOrientation'];
        $this->dict[0x2010][0x0050] = ['CS','1','FilmSizeID'];
        $this->dict[0x2010][0x0052] = ['CS','1','PrinterResolutionID'];
        $this->dict[0x2010][0x0054] = ['CS','1','DefaultPrinterResolutionID'];
        $this->dict[0x2010][0x0060] = ['CS','1','MagnificationType'];
        $this->dict[0x2010][0x0080] = ['CS','1','SmoothingType'];
        $this->dict[0x2010][0x00A6] = ['CS','1','DefaultMagnificationType'];
        $this->dict[0x2010][0x00A7] = ['CS','1-n','OtherMagnificationTypesAvailable'];
        $this->dict[0x2010][0x00A8] = ['CS','1','DefaultSmoothingType'];
        $this->dict[0x2010][0x00A9] = ['CS','1-n','OtherSmoothingTypesAvailable'];
        $this->dict[0x2010][0x0100] = ['CS','1','BorderDensity'];
        $this->dict[0x2010][0x0110] = ['CS','1','EmptyImageDensity'];
        $this->dict[0x2010][0x0120] = ['US','1','MinDensity'];
        $this->dict[0x2010][0x0130] = ['US','1','MaxDensity'];
        $this->dict[0x2010][0x0140] = ['CS','1','Trim'];
        $this->dict[0x2010][0x0150] = ['ST','1','ConfigurationInformation'];
        $this->dict[0x2010][0x0152] = ['LT','1','ConfigurationInformationDescription'];
        $this->dict[0x2010][0x0154] = ['IS','1','MaximumCollatedFilms'];
        $this->dict[0x2010][0x015E] = ['US','1','Illumination'];
        $this->dict[0x2010][0x0160] = ['US','1','ReflectedAmbientLight'];
        $this->dict[0x2010][0x0376] = ['DS','2','PrinterPixelSpacing'];
        $this->dict[0x2010][0x0500] = ['SQ','1','ReferencedFilmSessionSequence'];
        $this->dict[0x2010][0x0510] = ['SQ','1','ReferencedImageBoxSequence'];
        $this->dict[0x2010][0x0520] = ['SQ','1','ReferencedBasicAnnotationBoxSequence'];
        $this->dict[0x2020][0x0000] = ['UL','1','ImageBoxGroupLength'];
        $this->dict[0x2020][0x0010] = ['US','1','ImageBoxPosition'];
        $this->dict[0x2020][0x0020] = ['CS','1','Polarity'];
        $this->dict[0x2020][0x0030] = ['DS','1','RequestedImageSize'];
        $this->dict[0x2020][0x0040] = ['CS','1','RequestedDecimateCropBehavior'];
        $this->dict[0x2020][0x0050] = ['CS','1','RequestedResolutionID'];
        $this->dict[0x2020][0x00A0] = ['CS','1','RequestedImageSizeFlag'];
        $this->dict[0x2020][0x00A2] = ['CS','1','DecimateCropResult'];
        $this->dict[0x2020][0x0110] = ['SQ','1','PreformattedGrayscaleImageSequence'];
        $this->dict[0x2020][0x0111] = ['SQ','1','PreformattedColorImageSequence'];
        $this->dict[0x2020][0x0130] = ['SQ','1','ReferencedImageOverlayBoxSequence'];
        $this->dict[0x2020][0x0140] = ['SQ','1','ReferencedVOILUTBoxSequence'];
        $this->dict[0x2030][0x0000] = ['UL','1','AnnotationGroupLength'];
        $this->dict[0x2030][0x0010] = ['US','1','AnnotationPosition'];
        $this->dict[0x2030][0x0020] = ['LO','1','TextString'];
        $this->dict[0x2040][0x0000] = ['UL','1','OverlayBoxGroupLength'];
        $this->dict[0x2040][0x0010] = ['SQ','1','ReferencedOverlayPlaneSequence'];
        $this->dict[0x2040][0x0011] = ['US','9','ReferencedOverlayPlaneGroups'];
        $this->dict[0x2040][0x0020] = ['SQ','1','OverlayPixelDataSequence'];
        $this->dict[0x2040][0x0060] = ['CS','1','OverlayMagnificationType'];
        $this->dict[0x2040][0x0070] = ['CS','1','OverlaySmoothingType'];
        $this->dict[0x2040][0x0072] = ['CS','1','OverlayOrImageMagnification'];
        $this->dict[0x2040][0x0074] = ['US','1','MagnifyToNumberOfColumns'];
        $this->dict[0x2040][0x0080] = ['CS','1','OverlayForegroundDensity'];
        $this->dict[0x2040][0x0082] = ['CS','1','OverlayBackgroundDensity'];
        $this->dict[0x2040][0x0090] = ['CS','1','OverlayMode'];
        $this->dict[0x2040][0x0100] = ['CS','1','ThresholdDensity'];
        $this->dict[0x2040][0x0500] = ['SQ','1','ReferencedOverlayImageBoxSequence'];
        $this->dict[0x2050][0x0000] = ['UL','1','PresentationLUTGroupLength'];
        $this->dict[0x2050][0x0010] = ['SQ','1','PresentationLUTSequence'];
        $this->dict[0x2050][0x0020] = ['CS','1','PresentationLUTShape'];
        $this->dict[0x2050][0x0500] = ['SQ','1','ReferencedPresentationLUTSequence'];
        $this->dict[0x2100][0x0000] = ['UL','1','PrintJobGroupLength'];
        $this->dict[0x2100][0x0010] = ['SH','1','PrintJobID'];
        $this->dict[0x2100][0x0020] = ['CS','1','ExecutionStatus'];
        $this->dict[0x2100][0x0030] = ['CS','1','ExecutionStatusInfo'];
        $this->dict[0x2100][0x0040] = ['DA','1','CreationDate'];
        $this->dict[0x2100][0x0050] = ['TM','1','CreationTime'];
        $this->dict[0x2100][0x0070] = ['AE','1','Originator'];
        $this->dict[0x2100][0x0140] = ['AE','1','DestinationAE'];
        $this->dict[0x2100][0x0160] = ['SH','1','OwnerID'];
        $this->dict[0x2100][0x0170] = ['IS','1','NumberOfFilms'];
        $this->dict[0x2100][0x0500] = ['SQ','1','ReferencedPrintJobSequence'];
        $this->dict[0x2110][0x0000] = ['UL','1','PrinterGroupLength'];
        $this->dict[0x2110][0x0010] = ['CS','1','PrinterStatus'];
        $this->dict[0x2110][0x0020] = ['CS','1','PrinterStatusInfo'];
        $this->dict[0x2110][0x0030] = ['LO','1','PrinterName'];
        $this->dict[0x2110][0x0099] = ['SH','1','PrintQueueID'];
        $this->dict[0x2120][0x0000] = ['UL','1','QueueGroupLength'];
        $this->dict[0x2120][0x0010] = ['CS','1','QueueStatus'];
        $this->dict[0x2120][0x0050] = ['SQ','1','PrintJobDescriptionSequence'];
        $this->dict[0x2120][0x0070] = ['SQ','1','QueueReferencedPrintJobSequence'];
        $this->dict[0x2130][0x0000] = ['UL','1','PrintContentGroupLength'];
        $this->dict[0x2130][0x0010] = ['SQ','1','PrintManagementCapabilitiesSequence'];
        $this->dict[0x2130][0x0015] = ['SQ','1','PrinterCharacteristicsSequence'];
        $this->dict[0x2130][0x0030] = ['SQ','1','FilmBoxContentSequence'];
        $this->dict[0x2130][0x0040] = ['SQ','1','ImageBoxContentSequence'];
        $this->dict[0x2130][0x0050] = ['SQ','1','AnnotationContentSequence'];
        $this->dict[0x2130][0x0060] = ['SQ','1','ImageOverlayBoxContentSequence'];
        $this->dict[0x2130][0x0080] = ['SQ','1','PresentationLUTContentSequence'];
        $this->dict[0x2130][0x00A0] = ['SQ','1','ProposedStudySequence'];
        $this->dict[0x2130][0x00C0] = ['SQ','1','OriginalImageSequence'];
        $this->dict[0x3002][0x0000] = ['UL','1','RTImageGroupLength'];
        $this->dict[0x3002][0x0002] = ['SH','1','RTImageLabel'];
        $this->dict[0x3002][0x0003] = ['LO','1','RTImageName'];
        $this->dict[0x3002][0x0004] = ['ST','1','RTImageDescription'];
        $this->dict[0x3002][0x000A] = ['CS','1','ReportedValuesOrigin'];
        $this->dict[0x3002][0x000C] = ['CS','1','RTImagePlane'];
        $this->dict[0x3002][0x000D] = ['DS','3','XRayImageReceptorTranslation'];
        $this->dict[0x3002][0x000E] = ['DS','1','XRayImageReceptorAngle'];
        $this->dict[0x3002][0x0010] = ['DS','6','RTImageOrientation'];
        $this->dict[0x3002][0x0011] = ['DS','2','ImagePlanePixelSpacing'];
        $this->dict[0x3002][0x0012] = ['DS','2','RTImagePosition'];
        $this->dict[0x3002][0x0020] = ['SH','1','RadiationMachineName'];
        $this->dict[0x3002][0x0022] = ['DS','1','RadiationMachineSAD'];
        $this->dict[0x3002][0x0024] = ['DS','1','RadiationMachineSSD'];
        $this->dict[0x3002][0x0026] = ['DS','1','RTImageSID'];
        $this->dict[0x3002][0x0028] = ['DS','1','SourceToReferenceObjectDistance'];
        $this->dict[0x3002][0x0029] = ['IS','1','FractionNumber'];
        $this->dict[0x3002][0x0030] = ['SQ','1','ExposureSequence'];
        $this->dict[0x3002][0x0032] = ['DS','1','MetersetExposure'];
        $this->dict[0x3004][0x0000] = ['UL','1','RTDoseGroupLength'];
        $this->dict[0x3004][0x0001] = ['CS','1','DVHType'];
        $this->dict[0x3004][0x0002] = ['CS','1','DoseUnits'];
        $this->dict[0x3004][0x0004] = ['CS','1','DoseType'];
        $this->dict[0x3004][0x0006] = ['LO','1','DoseComment'];
        $this->dict[0x3004][0x0008] = ['DS','3','NormalizationPoint'];
        $this->dict[0x3004][0x000A] = ['CS','1','DoseSummationType'];
        $this->dict[0x3004][0x000C] = ['DS','2-n','GridFrameOffsetVector'];
        $this->dict[0x3004][0x000E] = ['DS','1','DoseGridScaling'];
        $this->dict[0x3004][0x0010] = ['SQ','1','RTDoseROISequence'];
        $this->dict[0x3004][0x0012] = ['DS','1','DoseValue'];
        $this->dict[0x3004][0x0040] = ['DS','3','DVHNormalizationPoint'];
        $this->dict[0x3004][0x0042] = ['DS','1','DVHNormalizationDoseValue'];
        $this->dict[0x3004][0x0050] = ['SQ','1','DVHSequence'];
        $this->dict[0x3004][0x0052] = ['DS','1','DVHDoseScaling'];
        $this->dict[0x3004][0x0054] = ['CS','1','DVHVolumeUnits'];
        $this->dict[0x3004][0x0056] = ['IS','1','DVHNumberOfBins'];
        $this->dict[0x3004][0x0058] = ['DS','2-2n','DVHData'];
        $this->dict[0x3004][0x0060] = ['SQ','1','DVHReferencedROISequence'];
        $this->dict[0x3004][0x0062] = ['CS','1','DVHROIContributionType'];
        $this->dict[0x3004][0x0070] = ['DS','1','DVHMinimumDose'];
        $this->dict[0x3004][0x0072] = ['DS','1','DVHMaximumDose'];
        $this->dict[0x3004][0x0074] = ['DS','1','DVHMeanDose'];
        $this->dict[0x3006][0x0000] = ['UL','1','RTStructureSetGroupLength'];
        $this->dict[0x3006][0x0002] = ['SH','1','StructureSetLabel'];
        $this->dict[0x3006][0x0004] = ['LO','1','StructureSetName'];
        $this->dict[0x3006][0x0006] = ['ST','1','StructureSetDescription'];
        $this->dict[0x3006][0x0008] = ['DA','1','StructureSetDate'];
        $this->dict[0x3006][0x0009] = ['TM','1','StructureSetTime'];
        $this->dict[0x3006][0x0010] = ['SQ','1','ReferencedFrameOfReferenceSequence'];
        $this->dict[0x3006][0x0012] = ['SQ','1','RTReferencedStudySequence'];
        $this->dict[0x3006][0x0014] = ['SQ','1','RTReferencedSeriesSequence'];
        $this->dict[0x3006][0x0016] = ['SQ','1','ContourImageSequence'];
        $this->dict[0x3006][0x0020] = ['SQ','1','StructureSetROISequence'];
        $this->dict[0x3006][0x0022] = ['IS','1','ROINumber'];
        $this->dict[0x3006][0x0024] = ['UI','1','ReferencedFrameOfReferenceUID'];
        $this->dict[0x3006][0x0026] = ['LO','1','ROIName'];
        $this->dict[0x3006][0x0028] = ['ST','1','ROIDescription'];
        $this->dict[0x3006][0x002A] = ['IS','3','ROIDisplayColor'];
        $this->dict[0x3006][0x002C] = ['DS','1','ROIVolume'];
        $this->dict[0x3006][0x0030] = ['SQ','1','RTRelatedROISequence'];
        $this->dict[0x3006][0x0033] = ['CS','1','RTROIRelationship'];
        $this->dict[0x3006][0x0036] = ['CS','1','ROIGenerationAlgorithm'];
        $this->dict[0x3006][0x0038] = ['LO','1','ROIGenerationDescription'];
        $this->dict[0x3006][0x0039] = ['SQ','1','ROIContourSequence'];
        $this->dict[0x3006][0x0040] = ['SQ','1','ContourSequence'];
        $this->dict[0x3006][0x0042] = ['CS','1','ContourGeometricType'];
        $this->dict[0x3006][0x0044] = ['DS','1','ContourSlabThickness'];
        $this->dict[0x3006][0x0045] = ['DS','3','ContourOffsetVector'];
        $this->dict[0x3006][0x0046] = ['IS','1','NumberOfContourPoints'];
        $this->dict[0x3006][0x0048] = ['IS','1','ContourNumber'];
        $this->dict[0x3006][0x0049] = ['IS','1-n','AttachedContours'];
        $this->dict[0x3006][0x0050] = ['DS','3-3n','ContourData'];
        $this->dict[0x3006][0x0080] = ['SQ','1','RTROIObservationsSequence'];
        $this->dict[0x3006][0x0082] = ['IS','1','ObservationNumber'];
        $this->dict[0x3006][0x0084] = ['IS','1','ReferencedROINumber'];
        $this->dict[0x3006][0x0085] = ['SH','1','ROIObservationLabel'];
        $this->dict[0x3006][0x0086] = ['SQ','1','RTROIIdentificationCodeSequence'];
        $this->dict[0x3006][0x0088] = ['ST','1','ROIObservationDescription'];
        $this->dict[0x3006][0x00A0] = ['SQ','1','RelatedRTROIObservationsSequence'];
        $this->dict[0x3006][0x00A4] = ['CS','1','RTROIInterpretedType'];
        $this->dict[0x3006][0x00A6] = ['PN','1','ROIInterpreter'];
        $this->dict[0x3006][0x00B0] = ['SQ','1','ROIPhysicalPropertiesSequence'];
        $this->dict[0x3006][0x00B2] = ['CS','1','ROIPhysicalProperty'];
        $this->dict[0x3006][0x00B4] = ['DS','1','ROIPhysicalPropertyValue'];
        $this->dict[0x3006][0x00C0] = ['SQ','1','FrameOfReferenceRelationshipSequence'];
        $this->dict[0x3006][0x00C2] = ['UI','1','RelatedFrameOfReferenceUID'];
        $this->dict[0x3006][0x00C4] = ['CS','1','FrameOfReferenceTransformationType'];
        $this->dict[0x3006][0x00C6] = ['DS','16','FrameOfReferenceTransformationMatrix'];
        $this->dict[0x3006][0x00C8] = ['LO','1','FrameOfReferenceTransformationComment'];
        $this->dict[0x3008][0x0010] = ['SQ','1','MeasuredDoseReferenceSequence'];
        $this->dict[0x3008][0x0012] = ['ST','1','MeasuredDoseDescription'];
        $this->dict[0x3008][0x0014] = ['CS','1','MeasuredDoseType'];
        $this->dict[0x3008][0x0016] = ['DS','1','MeasuredDoseValue'];
        $this->dict[0x3008][0x0020] = ['SQ','1','TreatmentSessionBeamSequence'];
        $this->dict[0x3008][0x0022] = ['IS','1','CurrentFractionNumber'];
        $this->dict[0x3008][0x0024] = ['DA','1','TreatmentControlPointDate'];
        $this->dict[0x3008][0x0025] = ['TM','1','TreatmentControlPointTime'];
        $this->dict[0x3008][0x002A] = ['CS','1','TreatmentTerminationStatus'];
        $this->dict[0x3008][0x002B] = ['SH','1','TreatmentTerminationCode'];
        $this->dict[0x3008][0x002C] = ['CS','1','TreatmentVerificationStatus'];
        $this->dict[0x3008][0x0030] = ['SQ','1','ReferencedTreatmentRecordSequence'];
        $this->dict[0x3008][0x0032] = ['DS','1','SpecifiedPrimaryMeterset'];
        $this->dict[0x3008][0x0033] = ['DS','1','SpecifiedSecondaryMeterset'];
        $this->dict[0x3008][0x0036] = ['DS','1','DeliveredPrimaryMeterset'];
        $this->dict[0x3008][0x0037] = ['DS','1','DeliveredSecondaryMeterset'];
        $this->dict[0x3008][0x003A] = ['DS','1','SpecifiedTreatmentTime'];
        $this->dict[0x3008][0x003B] = ['DS','1','DeliveredTreatmentTime'];
        $this->dict[0x3008][0x0040] = ['SQ','1','ControlPointDeliverySequence'];
        $this->dict[0x3008][0x0042] = ['DS','1','SpecifiedMeterset'];
        $this->dict[0x3008][0x0044] = ['DS','1','DeliveredMeterset'];
        $this->dict[0x3008][0x0048] = ['DS','1','DoseRateDelivered'];
        $this->dict[0x3008][0x0050] = ['SQ','1','TreatmentSummaryCalculatedDoseReferenceSequence'];
        $this->dict[0x3008][0x0052] = ['DS','1','CumulativeDosetoDoseReference'];
        $this->dict[0x3008][0x0054] = ['DA','1','FirstTreatmentDate'];
        $this->dict[0x3008][0x0056] = ['DA','1','MostRecentTreatmentDate'];
        $this->dict[0x3008][0x005A] = ['IS','1','NumberofFractionsDelivered'];
        $this->dict[0x3008][0x0060] = ['SQ','1','OverrideSequence'];
        $this->dict[0x3008][0x0062] = ['AT','1','OverrideParameterPointer'];
        $this->dict[0x3008][0x0064] = ['IS','1','MeasuredDoseReferenceNumber'];
        $this->dict[0x3008][0x0066] = ['ST','1','OverrideReason'];
        $this->dict[0x3008][0x0070] = ['SQ','1','CalculatedDoseReferenceSequence'];
        $this->dict[0x3008][0x0072] = ['IS','1','CalculatedDoseReferenceNumber'];
        $this->dict[0x3008][0x0074] = ['ST','1','CalculatedDoseReferenceDescription'];
        $this->dict[0x3008][0x0076] = ['DS','1','CalculatedDoseReferenceDoseValue'];
        $this->dict[0x3008][0x0078] = ['DS','1','StartMeterset'];
        $this->dict[0x3008][0x007A] = ['DS','1','EndMeterset'];
        $this->dict[0x3008][0x0080] = ['SQ','1','ReferencedMeasuredDoseReferenceSequence'];
        $this->dict[0x3008][0x0082] = ['IS','1','ReferencedMeasuredDoseReferenceNumber'];
        $this->dict[0x3008][0x0090] = ['SQ','1','ReferencedCalculatedDoseReferenceSequence'];
        $this->dict[0x3008][0x0092] = ['IS','1','ReferencedCalculatedDoseReferenceNumber'];
        $this->dict[0x3008][0x00A0] = ['SQ','1','BeamLimitingDeviceLeafPairsSequence'];
        $this->dict[0x3008][0x00B0] = ['SQ','1','RecordedWedgeSequence'];
        $this->dict[0x3008][0x00C0] = ['SQ','1','RecordedCompensatorSequence'];
        $this->dict[0x3008][0x00D0] = ['SQ','1','RecordedBlockSequence'];
        $this->dict[0x3008][0x00E0] = ['SQ','1','TreatmentSummaryMeasuredDoseReferenceSequence'];
        $this->dict[0x3008][0x0100] = ['SQ','1','RecordedSourceSequence'];
        $this->dict[0x3008][0x0105] = ['LO','1','SourceSerialNumber'];
        $this->dict[0x3008][0x0110] = ['SQ','1','TreatmentSessionApplicationSetupSequence'];
        $this->dict[0x3008][0x0116] = ['CS','1','ApplicationSetupCheck'];
        $this->dict[0x3008][0x0120] = ['SQ','1','RecordedBrachyAccessoryDeviceSequence'];
        $this->dict[0x3008][0x0122] = ['IS','1','ReferencedBrachyAccessoryDeviceNumber'];
        $this->dict[0x3008][0x0130] = ['SQ','1','RecordedChannelSequence'];
        $this->dict[0x3008][0x0132] = ['DS','1','SpecifiedChannelTotalTime'];
        $this->dict[0x3008][0x0134] = ['DS','1','DeliveredChannelTotalTime'];
        $this->dict[0x3008][0x0136] = ['IS','1','SpecifiedNumberofPulses'];
        $this->dict[0x3008][0x0138] = ['IS','1','DeliveredNumberofPulses'];
        $this->dict[0x3008][0x013A] = ['DS','1','SpecifiedPulseRepetitionInterval'];
        $this->dict[0x3008][0x013C] = ['DS','1','DeliveredPulseRepetitionInterval'];
        $this->dict[0x3008][0x0140] = ['SQ','1','RecordedSourceApplicatorSequence'];
        $this->dict[0x3008][0x0142] = ['IS','1','ReferencedSourceApplicatorNumber'];
        $this->dict[0x3008][0x0150] = ['SQ','1','RecordedChannelShieldSequence'];
        $this->dict[0x3008][0x0152] = ['IS','1','ReferencedChannelShieldNumber'];
        $this->dict[0x3008][0x0160] = ['SQ','1','BrachyControlPointDeliveredSequence'];
        $this->dict[0x3008][0x0162] = ['DA','1','SafePositionExitDate'];
        $this->dict[0x3008][0x0164] = ['TM','1','SafePositionExitTime'];
        $this->dict[0x3008][0x0166] = ['DA','1','SafePositionReturnDate'];
        $this->dict[0x3008][0x0168] = ['TM','1','SafePositionReturnTime'];
        $this->dict[0x3008][0x0200] = ['CS','1','CurrentTreatmentStatus'];
        $this->dict[0x3008][0x0202] = ['ST','1','TreatmentStatusComment'];
        $this->dict[0x3008][0x0220] = ['SQ','1','FractionGroupSummarySequence'];
        $this->dict[0x3008][0x0223] = ['IS','1','ReferencedFractionNumber'];
        $this->dict[0x3008][0x0224] = ['CS','1','FractionGroupType'];
        $this->dict[0x3008][0x0230] = ['CS','1','BeamStopperPosition'];
        $this->dict[0x3008][0x0240] = ['SQ','1','FractionStatusSummarySequence'];
        $this->dict[0x3008][0x0250] = ['DA','1','TreatmentDate'];
        $this->dict[0x3008][0x0251] = ['TM','1','TreatmentTime'];
        $this->dict[0x300A][0x0000] = ['UL','1','RTPlanGroupLength'];
        $this->dict[0x300A][0x0002] = ['SH','1','RTPlanLabel'];
        $this->dict[0x300A][0x0003] = ['LO','1','RTPlanName'];
        $this->dict[0x300A][0x0004] = ['ST','1','RTPlanDescription'];
        $this->dict[0x300A][0x0006] = ['DA','1','RTPlanDate'];
        $this->dict[0x300A][0x0007] = ['TM','1','RTPlanTime'];
        $this->dict[0x300A][0x0009] = ['LO','1-n','TreatmentProtocols'];
        $this->dict[0x300A][0x000A] = ['CS','1','TreatmentIntent'];
        $this->dict[0x300A][0x000B] = ['LO','1-n','TreatmentSites'];
        $this->dict[0x300A][0x000C] = ['CS','1','RTPlanGeometry'];
        $this->dict[0x300A][0x000E] = ['ST','1','PrescriptionDescription'];
        $this->dict[0x300A][0x0010] = ['SQ','1','DoseReferenceSequence'];
        $this->dict[0x300A][0x0012] = ['IS','1','DoseReferenceNumber'];
        $this->dict[0x300A][0x0014] = ['CS','1','DoseReferenceStructureType'];
        $this->dict[0x300A][0x0015] = ['CS','1','NominalBeamEnergyUnit'];
        $this->dict[0x300A][0x0016] = ['LO','1','DoseReferenceDescription'];
        $this->dict[0x300A][0x0018] = ['DS','3','DoseReferencePointCoordinates'];
        $this->dict[0x300A][0x001A] = ['DS','1','NominalPriorDose'];
        $this->dict[0x300A][0x0020] = ['CS','1','DoseReferenceType'];
        $this->dict[0x300A][0x0021] = ['DS','1','ConstraintWeight'];
        $this->dict[0x300A][0x0022] = ['DS','1','DeliveryWarningDose'];
        $this->dict[0x300A][0x0023] = ['DS','1','DeliveryMaximumDose'];
        $this->dict[0x300A][0x0025] = ['DS','1','TargetMinimumDose'];
        $this->dict[0x300A][0x0026] = ['DS','1','TargetPrescriptionDose'];
        $this->dict[0x300A][0x0027] = ['DS','1','TargetMaximumDose'];
        $this->dict[0x300A][0x0028] = ['DS','1','TargetUnderdoseVolumeFraction'];
        $this->dict[0x300A][0x002A] = ['DS','1','OrganAtRiskFullVolumeDose'];
        $this->dict[0x300A][0x002B] = ['DS','1','OrganAtRiskLimitDose'];
        $this->dict[0x300A][0x002C] = ['DS','1','OrganAtRiskMaximumDose'];
        $this->dict[0x300A][0x002D] = ['DS','1','OrganAtRiskOverdoseVolumeFraction'];
        $this->dict[0x300A][0x0040] = ['SQ','1','ToleranceTableSequence'];
        $this->dict[0x300A][0x0042] = ['IS','1','ToleranceTableNumber'];
        $this->dict[0x300A][0x0043] = ['SH','1','ToleranceTableLabel'];
        $this->dict[0x300A][0x0044] = ['DS','1','GantryAngleTolerance'];
        $this->dict[0x300A][0x0046] = ['DS','1','BeamLimitingDeviceAngleTolerance'];
        $this->dict[0x300A][0x0048] = ['SQ','1','BeamLimitingDeviceToleranceSequence'];
        $this->dict[0x300A][0x004A] = ['DS','1','BeamLimitingDevicePositionTolerance'];
        $this->dict[0x300A][0x004C] = ['DS','1','PatientSupportAngleTolerance'];
        $this->dict[0x300A][0x004E] = ['DS','1','TableTopEccentricAngleTolerance'];
        $this->dict[0x300A][0x0051] = ['DS','1','TableTopVerticalPositionTolerance'];
        $this->dict[0x300A][0x0052] = ['DS','1','TableTopLongitudinalPositionTolerance'];
        $this->dict[0x300A][0x0053] = ['DS','1','TableTopLateralPositionTolerance'];
        $this->dict[0x300A][0x0055] = ['CS','1','RTPlanRelationship'];
        $this->dict[0x300A][0x0070] = ['SQ','1','FractionGroupSequence'];
        $this->dict[0x300A][0x0071] = ['IS','1','FractionGroupNumber'];
        $this->dict[0x300A][0x0078] = ['IS','1','NumberOfFractionsPlanned'];
        $this->dict[0x300A][0x0079] = ['IS','1','NumberOfFractionsPerDay'];
        $this->dict[0x300A][0x007A] = ['IS','1','RepeatFractionCycleLength'];
        $this->dict[0x300A][0x007B] = ['LT','1','FractionPattern'];
        $this->dict[0x300A][0x0080] = ['IS','1','NumberOfBeams'];
        $this->dict[0x300A][0x0082] = ['DS','3','BeamDoseSpecificationPoint'];
        $this->dict[0x300A][0x0084] = ['DS','1','BeamDose'];
        $this->dict[0x300A][0x0086] = ['DS','1','BeamMeterset'];
        $this->dict[0x300A][0x00A0] = ['IS','1','NumberOfBrachyApplicationSetups'];
        $this->dict[0x300A][0x00A2] = ['DS','3','BrachyApplicationSetupDoseSpecificationPoint'];
        $this->dict[0x300A][0x00A4] = ['DS','1','BrachyApplicationSetupDose'];
        $this->dict[0x300A][0x00B0] = ['SQ','1','BeamSequence'];
        $this->dict[0x300A][0x00B2] = ['SH','1','TreatmentMachineName'];
        $this->dict[0x300A][0x00B3] = ['CS','1','PrimaryDosimeterUnit'];
        $this->dict[0x300A][0x00B4] = ['DS','1','SourceAxisDistance'];
        $this->dict[0x300A][0x00B6] = ['SQ','1','BeamLimitingDeviceSequence'];
        $this->dict[0x300A][0x00B8] = ['CS','1','RTBeamLimitingDeviceType'];
        $this->dict[0x300A][0x00BA] = ['DS','1','SourceToBeamLimitingDeviceDistance'];
        $this->dict[0x300A][0x00BC] = ['IS','1','NumberOfLeafJawPairs'];
        $this->dict[0x300A][0x00BE] = ['DS','3-n','LeafPositionBoundaries'];
        $this->dict[0x300A][0x00C0] = ['IS','1','BeamNumber'];
        $this->dict[0x300A][0x00C2] = ['LO','1','BeamName'];
        $this->dict[0x300A][0x00C3] = ['ST','1','BeamDescription'];
        $this->dict[0x300A][0x00C4] = ['CS','1','BeamType'];
        $this->dict[0x300A][0x00C6] = ['CS','1','RadiationType'];
        $this->dict[0x300A][0x00C8] = ['IS','1','ReferenceImageNumber'];
        $this->dict[0x300A][0x00CA] = ['SQ','1','PlannedVerificationImageSequence'];
        $this->dict[0x300A][0x00CC] = ['LO','1-n','ImagingDeviceSpecificAcquisitionParameters'];
        $this->dict[0x300A][0x00CE] = ['CS','1','TreatmentDeliveryType'];
        $this->dict[0x300A][0x00D0] = ['IS','1','NumberOfWedges'];
        $this->dict[0x300A][0x00D1] = ['SQ','1','WedgeSequence'];
        $this->dict[0x300A][0x00D2] = ['IS','1','WedgeNumber'];
        $this->dict[0x300A][0x00D3] = ['CS','1','WedgeType'];
        $this->dict[0x300A][0x00D4] = ['SH','1','WedgeID'];
        $this->dict[0x300A][0x00D5] = ['IS','1','WedgeAngle'];
        $this->dict[0x300A][0x00D6] = ['DS','1','WedgeFactor'];
        $this->dict[0x300A][0x00D8] = ['DS','1','WedgeOrientation'];
        $this->dict[0x300A][0x00DA] = ['DS','1','SourceToWedgeTrayDistance'];
        $this->dict[0x300A][0x00E0] = ['IS','1','NumberOfCompensators'];
        $this->dict[0x300A][0x00E1] = ['SH','1','MaterialID'];
        $this->dict[0x300A][0x00E2] = ['DS','1','TotalCompensatorTrayFactor'];
        $this->dict[0x300A][0x00E3] = ['SQ','1','CompensatorSequence'];
        $this->dict[0x300A][0x00E4] = ['IS','1','CompensatorNumber'];
        $this->dict[0x300A][0x00E5] = ['SH','1','CompensatorID'];
        $this->dict[0x300A][0x00E6] = ['DS','1','SourceToCompensatorTrayDistance'];
        $this->dict[0x300A][0x00E7] = ['IS','1','CompensatorRows'];
        $this->dict[0x300A][0x00E8] = ['IS','1','CompensatorColumns'];
        $this->dict[0x300A][0x00E9] = ['DS','2','CompensatorPixelSpacing'];
        $this->dict[0x300A][0x00EA] = ['DS','2','CompensatorPosition'];
        $this->dict[0x300A][0x00EB] = ['DS','1-n','CompensatorTransmissionData'];
        $this->dict[0x300A][0x00EC] = ['DS','1-n','CompensatorThicknessData'];
        $this->dict[0x300A][0x00ED] = ['IS','1','NumberOfBoli'];
        $this->dict[0x300A][0x00EE] = ['CS','1','CompensatorType'];
        $this->dict[0x300A][0x00F0] = ['IS','1','NumberOfBlocks'];
        $this->dict[0x300A][0x00F2] = ['DS','1','TotalBlockTrayFactor'];
        $this->dict[0x300A][0x00F4] = ['SQ','1','BlockSequence'];
        $this->dict[0x300A][0x00F5] = ['SH','1','BlockTrayID'];
        $this->dict[0x300A][0x00F6] = ['DS','1','SourceToBlockTrayDistance'];
        $this->dict[0x300A][0x00F8] = ['CS','1','BlockType'];
        $this->dict[0x300A][0x00FA] = ['CS','1','BlockDivergence'];
        $this->dict[0x300A][0x00FC] = ['IS','1','BlockNumber'];
        $this->dict[0x300A][0x00FE] = ['LO','1','BlockName'];
        $this->dict[0x300A][0x0100] = ['DS','1','BlockThickness'];
        $this->dict[0x300A][0x0102] = ['DS','1','BlockTransmission'];
        $this->dict[0x300A][0x0104] = ['IS','1','BlockNumberOfPoints'];
        $this->dict[0x300A][0x0106] = ['DS','2-2n','BlockData'];
        $this->dict[0x300A][0x0107] = ['SQ','1','ApplicatorSequence'];
        $this->dict[0x300A][0x0108] = ['SH','1','ApplicatorID'];
        $this->dict[0x300A][0x0109] = ['CS','1','ApplicatorType'];
        $this->dict[0x300A][0x010A] = ['LO','1','ApplicatorDescription'];
        $this->dict[0x300A][0x010C] = ['DS','1','CumulativeDoseReferenceCoefficient'];
        $this->dict[0x300A][0x010E] = ['DS','1','FinalCumulativeMetersetWeight'];
        $this->dict[0x300A][0x0110] = ['IS','1','NumberOfControlPoints'];
        $this->dict[0x300A][0x0111] = ['SQ','1','ControlPointSequence'];
        $this->dict[0x300A][0x0112] = ['IS','1','ControlPointIndex'];
        $this->dict[0x300A][0x0114] = ['DS','1','NominalBeamEnergy'];
        $this->dict[0x300A][0x0115] = ['DS','1','DoseRateSet'];
        $this->dict[0x300A][0x0116] = ['SQ','1','WedgePositionSequence'];
        $this->dict[0x300A][0x0118] = ['CS','1','WedgePosition'];
        $this->dict[0x300A][0x011A] = ['SQ','1','BeamLimitingDevicePositionSequence'];
        $this->dict[0x300A][0x011C] = ['DS','2-2n','LeafJawPositions'];
        $this->dict[0x300A][0x011E] = ['DS','1','GantryAngle'];
        $this->dict[0x300A][0x011F] = ['CS','1','GantryRotationDirection'];
        $this->dict[0x300A][0x0120] = ['DS','1','BeamLimitingDeviceAngle'];
        $this->dict[0x300A][0x0121] = ['CS','1','BeamLimitingDeviceRotationDirection'];
        $this->dict[0x300A][0x0122] = ['DS','1','PatientSupportAngle'];
        $this->dict[0x300A][0x0123] = ['CS','1','PatientSupportRotationDirection'];
        $this->dict[0x300A][0x0124] = ['DS','1','TableTopEccentricAxisDistance'];
        $this->dict[0x300A][0x0125] = ['DS','1','TableTopEccentricAngle'];
        $this->dict[0x300A][0x0126] = ['CS','1','TableTopEccentricRotationDirection'];
        $this->dict[0x300A][0x0128] = ['DS','1','TableTopVerticalPosition'];
        $this->dict[0x300A][0x0129] = ['DS','1','TableTopLongitudinalPosition'];
        $this->dict[0x300A][0x012A] = ['DS','1','TableTopLateralPosition'];
        $this->dict[0x300A][0x012C] = ['DS','3','IsocenterPosition'];
        $this->dict[0x300A][0x012E] = ['DS','3','SurfaceEntryPoint'];
        $this->dict[0x300A][0x0130] = ['DS','1','SourceToSurfaceDistance'];
        $this->dict[0x300A][0x0134] = ['DS','1','CumulativeMetersetWeight'];
        $this->dict[0x300A][0x0180] = ['SQ','1','PatientSetupSequence'];
        $this->dict[0x300A][0x0182] = ['IS','1','PatientSetupNumber'];
        $this->dict[0x300A][0x0184] = ['LO','1','PatientAdditionalPosition'];
        $this->dict[0x300A][0x0190] = ['SQ','1','FixationDeviceSequence'];
        $this->dict[0x300A][0x0192] = ['CS','1','FixationDeviceType'];
        $this->dict[0x300A][0x0194] = ['SH','1','FixationDeviceLabel'];
        $this->dict[0x300A][0x0196] = ['ST','1','FixationDeviceDescription'];
        $this->dict[0x300A][0x0198] = ['SH','1','FixationDevicePosition'];
        $this->dict[0x300A][0x01A0] = ['SQ','1','ShieldingDeviceSequence'];
        $this->dict[0x300A][0x01A2] = ['CS','1','ShieldingDeviceType'];
        $this->dict[0x300A][0x01A4] = ['SH','1','ShieldingDeviceLabel'];
        $this->dict[0x300A][0x01A6] = ['ST','1','ShieldingDeviceDescription'];
        $this->dict[0x300A][0x01A8] = ['SH','1','ShieldingDevicePosition'];
        $this->dict[0x300A][0x01B0] = ['CS','1','SetupTechnique'];
        $this->dict[0x300A][0x01B2] = ['ST','1','SetupTechniqueDescription'];
        $this->dict[0x300A][0x01B4] = ['SQ','1','SetupDeviceSequence'];
        $this->dict[0x300A][0x01B6] = ['CS','1','SetupDeviceType'];
        $this->dict[0x300A][0x01B8] = ['SH','1','SetupDeviceLabel'];
        $this->dict[0x300A][0x01BA] = ['ST','1','SetupDeviceDescription'];
        $this->dict[0x300A][0x01BC] = ['DS','1','SetupDeviceParameter'];
        $this->dict[0x300A][0x01D0] = ['ST','1','SetupReferenceDescription'];
        $this->dict[0x300A][0x01D2] = ['DS','1','TableTopVerticalSetupDisplacement'];
        $this->dict[0x300A][0x01D4] = ['DS','1','TableTopLongitudinalSetupDisplacement'];
        $this->dict[0x300A][0x01D6] = ['DS','1','TableTopLateralSetupDisplacement'];
        $this->dict[0x300A][0x0200] = ['CS','1','BrachyTreatmentTechnique'];
        $this->dict[0x300A][0x0202] = ['CS','1','BrachyTreatmentType'];
        $this->dict[0x300A][0x0206] = ['SQ','1','TreatmentMachineSequence'];
        $this->dict[0x300A][0x0210] = ['SQ','1','SourceSequence'];
        $this->dict[0x300A][0x0212] = ['IS','1','SourceNumber'];
        $this->dict[0x300A][0x0214] = ['CS','1','SourceType'];
        $this->dict[0x300A][0x0216] = ['LO','1','SourceManufacturer'];
        $this->dict[0x300A][0x0218] = ['DS','1','ActiveSourceDiameter'];
        $this->dict[0x300A][0x021A] = ['DS','1','ActiveSourceLength'];
        $this->dict[0x300A][0x0222] = ['DS','1','SourceEncapsulationNominalThickness'];
        $this->dict[0x300A][0x0224] = ['DS','1','SourceEncapsulationNominalTransmission'];
        $this->dict[0x300A][0x0226] = ['LO','1','SourceIsotopeName'];
        $this->dict[0x300A][0x0228] = ['DS','1','SourceIsotopeHalfLife'];
        $this->dict[0x300A][0x022A] = ['DS','1','ReferenceAirKermaRate'];
        $this->dict[0x300A][0x022C] = ['DA','1','AirKermaRateReferenceDate'];
        $this->dict[0x300A][0x022E] = ['TM','1','AirKermaRateReferenceTime'];
        $this->dict[0x300A][0x0230] = ['SQ','1','ApplicationSetupSequence'];
        $this->dict[0x300A][0x0232] = ['CS','1','ApplicationSetupType'];
        $this->dict[0x300A][0x0234] = ['IS','1','ApplicationSetupNumber'];
        $this->dict[0x300A][0x0236] = ['LO','1','ApplicationSetupName'];
        $this->dict[0x300A][0x0238] = ['LO','1','ApplicationSetupManufacturer'];
        $this->dict[0x300A][0x0240] = ['IS','1','TemplateNumber'];
        $this->dict[0x300A][0x0242] = ['SH','1','TemplateType'];
        $this->dict[0x300A][0x0244] = ['LO','1','TemplateName'];
        $this->dict[0x300A][0x0250] = ['DS','1','TotalReferenceAirKerma'];
        $this->dict[0x300A][0x0260] = ['SQ','1','BrachyAccessoryDeviceSequence'];
        $this->dict[0x300A][0x0262] = ['IS','1','BrachyAccessoryDeviceNumber'];
        $this->dict[0x300A][0x0263] = ['SH','1','BrachyAccessoryDeviceID'];
        $this->dict[0x300A][0x0264] = ['CS','1','BrachyAccessoryDeviceType'];
        $this->dict[0x300A][0x0266] = ['LO','1','BrachyAccessoryDeviceName'];
        $this->dict[0x300A][0x026A] = ['DS','1','BrachyAccessoryDeviceNominalThickness'];
        $this->dict[0x300A][0x026C] = ['DS','1','BrachyAccessoryDeviceNominalTransmission'];
        $this->dict[0x300A][0x0280] = ['SQ','1','ChannelSequence'];
        $this->dict[0x300A][0x0282] = ['IS','1','ChannelNumber'];
        $this->dict[0x300A][0x0284] = ['DS','1','ChannelLength'];
        $this->dict[0x300A][0x0286] = ['DS','1','ChannelTotalTime'];
        $this->dict[0x300A][0x0288] = ['CS','1','SourceMovementType'];
        $this->dict[0x300A][0x028A] = ['IS','1','NumberOfPulses'];
        $this->dict[0x300A][0x028C] = ['DS','1','PulseRepetitionInterval'];
        $this->dict[0x300A][0x0290] = ['IS','1','SourceApplicatorNumber'];
        $this->dict[0x300A][0x0291] = ['SH','1','SourceApplicatorID'];
        $this->dict[0x300A][0x0292] = ['CS','1','SourceApplicatorType'];
        $this->dict[0x300A][0x0294] = ['LO','1','SourceApplicatorName'];
        $this->dict[0x300A][0x0296] = ['DS','1','SourceApplicatorLength'];
        $this->dict[0x300A][0x0298] = ['LO','1','SourceApplicatorManufacturer'];
        $this->dict[0x300A][0x029C] = ['DS','1','SourceApplicatorWallNominalThickness'];
        $this->dict[0x300A][0x029E] = ['DS','1','SourceApplicatorWallNominalTransmission'];
        $this->dict[0x300A][0x02A0] = ['DS','1','SourceApplicatorStepSize'];
        $this->dict[0x300A][0x02A2] = ['IS','1','TransferTubeNumber'];
        $this->dict[0x300A][0x02A4] = ['DS','1','TransferTubeLength'];
        $this->dict[0x300A][0x02B0] = ['SQ','1','ChannelShieldSequence'];
        $this->dict[0x300A][0x02B2] = ['IS','1','ChannelShieldNumber'];
        $this->dict[0x300A][0x02B3] = ['SH','1','ChannelShieldID'];
        $this->dict[0x300A][0x02B4] = ['LO','1','ChannelShieldName'];
        $this->dict[0x300A][0x02B8] = ['DS','1','ChannelShieldNominalThickness'];
        $this->dict[0x300A][0x02BA] = ['DS','1','ChannelShieldNominalTransmission'];
        $this->dict[0x300A][0x02C8] = ['DS','1','FinalCumulativeTimeWeight'];
        $this->dict[0x300A][0x02D0] = ['SQ','1','BrachyControlPointSequence'];
        $this->dict[0x300A][0x02D2] = ['DS','1','ControlPointRelativePosition'];
        $this->dict[0x300A][0x02D4] = ['DS','3','ControlPointDPosition'];
        $this->dict[0x300A][0x02D6] = ['DS','1','CumulativeTimeWeight'];
        $this->dict[0x300C][0x0000] = ['UL','1','RTRelationshipGroupLength'];
        $this->dict[0x300C][0x0002] = ['SQ','1','ReferencedRTPlanSequence'];
        $this->dict[0x300C][0x0004] = ['SQ','1','ReferencedBeamSequence'];
        $this->dict[0x300C][0x0006] = ['IS','1','ReferencedBeamNumber'];
        $this->dict[0x300C][0x0007] = ['IS','1','ReferencedReferenceImageNumber'];
        $this->dict[0x300C][0x0008] = ['DS','1','StartCumulativeMetersetWeight'];
        $this->dict[0x300C][0x0009] = ['DS','1','EndCumulativeMetersetWeight'];
        $this->dict[0x300C][0x000A] = ['SQ','1','ReferencedBrachyApplicationSetupSequence'];
        $this->dict[0x300C][0x000C] = ['IS','1','ReferencedBrachyApplicationSetupNumber'];
        $this->dict[0x300C][0x000E] = ['IS','1','ReferencedSourceNumber'];
        $this->dict[0x300C][0x0020] = ['SQ','1','ReferencedFractionGroupSequence'];
        $this->dict[0x300C][0x0022] = ['IS','1','ReferencedFractionGroupNumber'];
        $this->dict[0x300C][0x0040] = ['SQ','1','ReferencedVerificationImageSequence'];
        $this->dict[0x300C][0x0042] = ['SQ','1','ReferencedReferenceImageSequence'];
        $this->dict[0x300C][0x0050] = ['SQ','1','ReferencedDoseReferenceSequence'];
        $this->dict[0x300C][0x0051] = ['IS','1','ReferencedDoseReferenceNumber'];
        $this->dict[0x300C][0x0055] = ['SQ','1','BrachyReferencedDoseReferenceSequence'];
        $this->dict[0x300C][0x0060] = ['SQ','1','ReferencedStructureSetSequence'];
        $this->dict[0x300C][0x006A] = ['IS','1','ReferencedPatientSetupNumber'];
        $this->dict[0x300C][0x0080] = ['SQ','1','ReferencedDoseSequence'];
        $this->dict[0x300C][0x00A0] = ['IS','1','ReferencedToleranceTableNumber'];
        $this->dict[0x300C][0x00B0] = ['SQ','1','ReferencedBolusSequence'];
        $this->dict[0x300C][0x00C0] = ['IS','1','ReferencedWedgeNumber'];
        $this->dict[0x300C][0x00D0] = ['IS','1','ReferencedCompensatorNumber'];
        $this->dict[0x300C][0x00E0] = ['IS','1','ReferencedBlockNumber'];
        $this->dict[0x300C][0x00F0] = ['IS','1','ReferencedControlPointIndex'];
        $this->dict[0x300E][0x0000] = ['UL','1','RTApprovalGroupLength'];
        $this->dict[0x300E][0x0002] = ['CS','1','ApprovalStatus'];
        $this->dict[0x300E][0x0004] = ['DA','1','ReviewDate'];
        $this->dict[0x300E][0x0005] = ['TM','1','ReviewTime'];
        $this->dict[0x300E][0x0008] = ['PN','1','ReviewerName'];
        $this->dict[0x4000][0x0000] = ['UL','1','TextGroupLength'];
        $this->dict[0x4000][0x0010] = ['LT','1-n','TextArbitrary'];
        $this->dict[0x4000][0x4000] = ['LT','1-n','TextComments'];
        $this->dict[0x4008][0x0000] = ['UL','1','ResultsGroupLength'];
        $this->dict[0x4008][0x0040] = ['SH','1','ResultsID'];
        $this->dict[0x4008][0x0042] = ['LO','1','ResultsIDIssuer'];
        $this->dict[0x4008][0x0050] = ['SQ','1','ReferencedInterpretationSequence'];
        $this->dict[0x4008][0x0100] = ['DA','1','InterpretationRecordedDate'];
        $this->dict[0x4008][0x0101] = ['TM','1','InterpretationRecordedTime'];
        $this->dict[0x4008][0x0102] = ['PN','1','InterpretationRecorder'];
        $this->dict[0x4008][0x0103] = ['LO','1','ReferenceToRecordedSound'];
        $this->dict[0x4008][0x0108] = ['DA','1','InterpretationTranscriptionDate'];
        $this->dict[0x4008][0x0109] = ['TM','1','InterpretationTranscriptionTime'];
        $this->dict[0x4008][0x010A] = ['PN','1','InterpretationTranscriber'];
        $this->dict[0x4008][0x010B] = ['ST','1','InterpretationText'];
        $this->dict[0x4008][0x010C] = ['PN','1','InterpretationAuthor'];
        $this->dict[0x4008][0x0111] = ['SQ','1','InterpretationApproverSequence'];
        $this->dict[0x4008][0x0112] = ['DA','1','InterpretationApprovalDate'];
        $this->dict[0x4008][0x0113] = ['TM','1','InterpretationApprovalTime'];
        $this->dict[0x4008][0x0114] = ['PN','1','PhysicianApprovingInterpretation'];
        $this->dict[0x4008][0x0115] = ['LT','1','InterpretationDiagnosisDescription'];
        $this->dict[0x4008][0x0117] = ['SQ','1','DiagnosisCodeSequence'];
        $this->dict[0x4008][0x0118] = ['SQ','1','ResultsDistributionListSequence'];
        $this->dict[0x4008][0x0119] = ['PN','1','DistributionName'];
        $this->dict[0x4008][0x011A] = ['LO','1','DistributionAddress'];
        $this->dict[0x4008][0x0200] = ['SH','1','InterpretationID'];
        $this->dict[0x4008][0x0202] = ['LO','1','InterpretationIDIssuer'];
        $this->dict[0x4008][0x0210] = ['CS','1','InterpretationTypeID'];
        $this->dict[0x4008][0x0212] = ['CS','1','InterpretationStatusID'];
        $this->dict[0x4008][0x0300] = ['ST','1','Impressions'];
        $this->dict[0x4008][0x4000] = ['ST','1','ResultsComments'];
        $this->dict[0x5000][0x0000] = ['UL','1','CurveGroupLength'];
        $this->dict[0x5000][0x0005] = ['US','1','CurveDimensions'];
        $this->dict[0x5000][0x0010] = ['US','1','NumberOfPoints'];
        $this->dict[0x5000][0x0020] = ['CS','1','TypeOfData'];
        $this->dict[0x5000][0x0022] = ['LO','1','CurveDescription'];
        $this->dict[0x5000][0x0030] = ['SH','1-n','AxisUnits'];
        $this->dict[0x5000][0x0040] = ['SH','1-n','AxisLabels'];
        $this->dict[0x5000][0x0103] = ['US','1','DataValueRepresentation'];
        $this->dict[0x5000][0x0104] = ['US','1-n','MinimumCoordinateValue'];
        $this->dict[0x5000][0x0105] = ['US','1-n','MaximumCoordinateValue'];
        $this->dict[0x5000][0x0106] = ['SH','1-n','CurveRange'];
        $this->dict[0x5000][0x0110] = ['US','1','CurveDataDescriptor'];
        $this->dict[0x5000][0x0112] = ['US','1','CoordinateStartValue'];
        $this->dict[0x5000][0x0114] = ['US','1','CoordinateStepValue'];
        $this->dict[0x5000][0x2000] = ['US','1','AudioType'];
        $this->dict[0x5000][0x2002] = ['US','1','AudioSampleFormat'];
        $this->dict[0x5000][0x2004] = ['US','1','NumberOfChannels'];
        $this->dict[0x5000][0x2006] = ['UL','1','NumberOfSamples'];
        $this->dict[0x5000][0x2008] = ['UL','1','SampleRate'];
        $this->dict[0x5000][0x200A] = ['UL','1','TotalTime'];
        $this->dict[0x5000][0x200C] = ['OX','1','AudioSampleData'];
        $this->dict[0x5000][0x200E] = ['LT','1','AudioComments'];
        $this->dict[0x5000][0x3000] = ['OX','1','CurveData'];
        $this->dict[0x5400][0x0100] = ['SQ','1','WaveformSequence'];
        $this->dict[0x5400][0x0110] = ['OW/OB','1','ChannelMinimumValue'];
        $this->dict[0x5400][0x0112] = ['OW/OB','1','ChannelMaximumValue'];
        $this->dict[0x5400][0x1004] = ['US','1','WaveformBitsAllocated'];
        $this->dict[0x5400][0x1006] = ['CS','1','WaveformSampleInterpretation'];
        $this->dict[0x5400][0x100A] = ['OW/OB','1','WaveformPaddingValue'];
        $this->dict[0x5400][0x1010] = ['OW/OB','1','WaveformData'];
        $this->dict[0x6000][0x0000] = ['UL','1','OverlayGroupLength'];
        $this->dict[0x6000][0x0010] = ['US','1','OverlayRows'];
        $this->dict[0x6000][0x0011] = ['US','1','OverlayColumns'];
        $this->dict[0x6000][0x0012] = ['US','1','OverlayPlanes'];
        $this->dict[0x6000][0x0015] = ['IS','1','OverlayNumberOfFrames'];
        $this->dict[0x6000][0x0040] = ['CS','1','OverlayType'];
        $this->dict[0x6000][0x0050] = ['SS','2','OverlayOrigin'];
        $this->dict[0x6000][0x0051] = ['US','1','OverlayImageFrameOrigin'];
        $this->dict[0x6000][0x0052] = ['US','1','OverlayPlaneOrigin'];
        $this->dict[0x6000][0x0060] = ['CS','1','OverlayCompressionCode'];
        $this->dict[0x6000][0x0061] = ['SH','1','OverlayCompressionOriginator'];
        $this->dict[0x6000][0x0062] = ['SH','1','OverlayCompressionLabel'];
        $this->dict[0x6000][0x0063] = ['SH','1','OverlayCompressionDescription'];
        $this->dict[0x6000][0x0066] = ['AT','1-n','OverlayCompressionStepPointers'];
        $this->dict[0x6000][0x0068] = ['US','1','OverlayRepeatInterval'];
        $this->dict[0x6000][0x0069] = ['US','1','OverlayBitsGrouped'];
        $this->dict[0x6000][0x0100] = ['US','1','OverlayBitsAllocated'];
        $this->dict[0x6000][0x0102] = ['US','1','OverlayBitPosition'];
        $this->dict[0x6000][0x0110] = ['CS','1','OverlayFormat'];
        $this->dict[0x6000][0x0200] = ['US','1','OverlayLocation'];
        $this->dict[0x6000][0x0800] = ['CS','1-n','OverlayCodeLabel'];
        $this->dict[0x6000][0x0802] = ['US','1','OverlayNumberOfTables'];
        $this->dict[0x6000][0x0803] = ['AT','1-n','OverlayCodeTableLocation'];
        $this->dict[0x6000][0x0804] = ['US','1','OverlayBitsForCodeWord'];
        $this->dict[0x6000][0x1100] = ['US','1','OverlayDescriptorGray'];
        $this->dict[0x6000][0x1101] = ['US','1','OverlayDescriptorRed'];
        $this->dict[0x6000][0x1102] = ['US','1','OverlayDescriptorGreen'];
        $this->dict[0x6000][0x1103] = ['US','1','OverlayDescriptorBlue'];
        $this->dict[0x6000][0x1200] = ['US','1-n','OverlayGray'];
        $this->dict[0x6000][0x1201] = ['US','1-n','OverlayRed'];
        $this->dict[0x6000][0x1202] = ['US','1-n','OverlayGreen'];
        $this->dict[0x6000][0x1203] = ['US','1-n','OverlayBlue'];
        $this->dict[0x6000][0x1301] = ['IS','1','ROIArea'];
        $this->dict[0x6000][0x1302] = ['DS','1','ROIMean'];
        $this->dict[0x6000][0x1303] = ['DS','1','ROIStandardDeviation'];
        $this->dict[0x6000][0x3000] = ['OW','1','OverlayData'];
        $this->dict[0x6000][0x4000] = ['LT','1-n','OverlayComments'];
        $this->dict[0x7F00][0x0000] = ['UL','1','VariablePixelDataGroupLength'];
        $this->dict[0x7F00][0x0010] = ['OX','1','VariablePixelData'];
        $this->dict[0x7F00][0x0011] = ['AT','1','VariableNextDataGroup'];
        $this->dict[0x7F00][0x0020] = ['OW','1-n','VariableCoefficientsSDVN'];
        $this->dict[0x7F00][0x0030] = ['OW','1-n','VariableCoefficientsSDHN'];
        $this->dict[0x7F00][0x0040] = ['OW','1-n','VariableCoefficientsSDDN'];
        $this->dict[0x7FE0][0x0000] = ['UL','1','PixelDataGroupLength'];
        $this->dict[0x7FE0][0x0010] = ['OX','1','PixelData'];
        $this->dict[0x7FE0][0x0020] = ['OW','1-n','CoefficientsSDVN'];
        $this->dict[0x7FE0][0x0030] = ['OW','1-n','CoefficientsSDHN'];
        $this->dict[0x7FE0][0x0040] = ['OW','1-n','CoefficientsSDDN'];
        $this->dict[0xFFFC][0xFFFC] = ['OB','1','DataSetTrailingPadding'];
        $this->dict[0xFFFE][0xE000] = ['NONE','1','Item'];
        $this->dict[0xFFFE][0xE00D] = ['NONE','1','ItemDelimitationItem'];
        $this->dict[0xFFFE][0xE0DD] = ['NONE','1','SequenceDelimitationItem'];
    }

    /**
    * Parse a DICOM file
    * Parse a DICOM file and get all of its header members
    *
    * @param string $infile The DICOM file to parse
    *
    * @return mixed true on success, PEAR_Error on failure
    */
    public function parse($infile)
    {
        $this->currentFile = $infile;
        $fh = @fopen($infile, "rb");
        if (!$fh) {
            return $this->raiseError("Could not open file $infile for reading");
        }
        $stat = fstat($fh);
        $this->fileLength = $stat[7];
  
        // Test for NEMA or DICOM file.  
        // If DICM, store initial preamble and leave file ptr at 0x84.
        $this->preambleBuff = fread($fh, 0x80);
        $buff = fread($fh, 4);
        $this->isDicm = ($buff === 'DICM');
        if (!$this->isDicm) {
            fseek($fh, 0);
        }
  
        // Fill in hash with header members from given file.
        while (ftell($fh) < $this->fileLength)
        {
            $element =& new File_DICOM_Element($fh, $this->dict);
            $this->elements[$element->group][$element->element] =& $element;
            $this->elementsByName[$element->name] =& $element;
        }
        fclose($fh);

        return true;
    }

    /**
    * Write current contents to a DICOM file.
    *
    * @param string $outfile The name of the file to write. If not given
    *                        it assumes the name of the file parsed.
    *                        If no file was parsed and no name is given
    *                        returns a PEAR_Error
    * @return mixed true on success, PEAR_Error on failure
    */
    public function write($outfile = '')
    {
        if ($outfile == '') {
            if (isset($this->currentFile)) {
                $outfile = $this->currentFile;
            } else {
                return $this->raiseError("File name not given (and no file currently open)");
            }
        }
        $fh = @fopen($outfile, "wb");
        if (!$fh) {
            return $this->raiseError("Could not open file $outfile for writing");
        }
  
        // Writing file from scratch will always fail for now
        if (!isset($this->preambleBuff)) {
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
        foreach (array_keys($this->elements) as $group) {
            foreach (array_keys($this->elements[$group]) as $element) {
                fwrite($fh, pack('v', $group));
                fwrite($fh, pack('v', $element));
                $code = $this->elements[$group][$element]->code;
                // Preserve the VR type from the file parsed
                if (($this->elements[$group][$element]->vrType == FILE_DICOM_VR_TYPE_EXPLICIT_32_BITS) or ($this->elements[$group][$element]->vrType == FILE_DICOM_VR_TYPE_EXPLICIT_16_BITS)) {
                    fwrite($fh, pack('CC', $code{0}, $code{1}));
                    // This is an OB, OW, SQ, UN or UT: 32 bit VL field.
                    if ($this->elements[$group][$element]->vrType == FILE_DICOM_VR_TYPE_EXPLICIT_32_BITS) {
                        fwrite($fh, pack('V', $this->elements[$group][$element]->length));
                    } else { // not using fixed length from VR!!!
                        fwrite($fh, pack('v', $this->elements[$group][$element]->length));
                    }
                } elseif ($this->elements[$group][$element]->vrType == FILE_DICOM_VR_TYPE_IMPLICIT) {
                    fwrite($fh, pack('V', $this->elements[$group][$element]->length));
                }
                switch ($code) {
                    // Decode unsigned longs and shorts.
                    case 'UL':
                        fwrite($fh, pack('V', $this->elements[$group][$element]->value));
                        break;
                    case 'US':
                        fwrite($fh, pack('v', $this->elements[$group][$element]->value));
                        break;
                    // Floats: Single and double precision.
                    case 'FL':
                        fwrite($fh, pack('f', $this->elements[$group][$element]->value));
                        break;
                    case 'FD':
                        fwrite($fh, pack('d', $this->elements[$group][$element]->value));
                        break;
                    // Binary data. Only save position. Is this right? 
                    case 'OW':
                    case 'OB':
                    case 'OX':
                        // Binary data. Value only contains position on the parsed file.
                        // Will fail when file name for writing is the same as for parsing.
                        $fh2 = @fopen($this->currentFile, "rb");
                        if (!$fh2) {
                            return $this->raiseError("Could not open file {$this->currentFile} for reading");
                        }
                        fseek($fh2, $this->elements[$group][$element]->value);
                        fwrite($fh, fread($fh2, $this->elements[$group][$element]->length));
                        fclose($fh2);
                        break;
                    default:
                        fwrite($fh, $this->elements[$group][$element]->value, $this->elements[$group][$element]->length);
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
    * @param mixed $gpOrName The group the DICOM element belongs to
    *                          (integer), or it's name (string)
    * @param integer $el       The identifier for the DICOM element
    *                          (unique inside a group)
    * @return mixed The value for the DICOM element on success, PEAR_Error on failure 
    */
    public function getValue($gpOrName, $el = null)
    {
        if (isset($el)) // retreive by group and element index
        {
            if (isset($this->elements[$gpOrName][$el])) {
                return $this->elements[$gpOrName][$el]->getValue();
            }
            else {
                return $this->raiseError("Element ($gpOrName,$el) not found");
            }
        }
        else // retreive by name
        {
            if (isset($this->elementsByName[$gpOrName])) {
                return $this->elementsByName[$gpOrName]->getValue();
            }
            else {
                return $this->raiseError("Element $gpOrName not found");
            }
        }
    }

    /**
    * Sets the value for a DICOM element
    * Only works with strings now.
    *
    * @param integer $gp The group the DICOM element belongs to
    * @param integer $el The identifier for the DICOM element (unique inside a group)
    */
    public function setValue($gp, $el, $value)
    {
        $this->elements[$gp][$el]->value = $value;
        $this->elements[$gp][$el]->length = strlen($value);
    }

    /**
    * Dumps the contents of the image inside the DICOM file 
    * (element 0x0010 from group 0x7FE0) to a PGM (Portable Gray Map) file.
    * Use with Caution!!. For a 8.5MB DICOM file on a P4 it takes 28 
    * seconds to dump it's image.
    *
    * @param string  $filename The file where to save the image
    * @return mixed true on success, PEAR_Error on failure.
    */
    public function dumpImage($filename)
    {
        $length = $this->elements[0x7FE0][0x0010]->length;
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
        $fhIn = @fopen($this->currentFile, "rb");
        if (!$fhIn) {
            return $this->raiseError("Could not open file {$this->currentFile} for reading");
        }
        fseek($fhIn, $pos);
        $blockSize = 4096;
        $blocks = ceil($length / $blockSize);
        for ($i = 0; $i < $blocks; $i++) {
            if ($i == $blocks - 1) { // last block
                $chunkLength = ($length % $blockSize) ? ($length % $blockSize) : $blockSize;
            } else {
                $chunkLength = $blockSize;
            }
            $chunk = fread($fhIn, $chunkLength);
            $pgm = '';
            $halfChunkLength = $chunkLength/2;
            $rr = unpack("v$halfChunkLength", $chunk);
            for ($j = 1; $j <= $halfChunkLength; $j++) {
                $pgm .= pack('C', $rr[$j] >> 4);
            }
            fwrite($fh, $pgm);
        }
        fclose($fhIn);
        fclose($fh);

        return true;
    }
}
