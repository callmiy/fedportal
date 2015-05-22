<?php

require_once(__DIR__ . '/../../../helpers/pdf-config.php');
require_once(__DIR__ . '/../../../helpers/tcpdf/tcpdf.php');
require_once(__DIR__ . '/../../../helpers/models/StudentProfile.php');

class TranscriptToPDF extends TCPDF
{
  /**
   * @var array
   */
  private $coursesScoresCellWidths = [
    14,             #sequence
    81,            #course title
    22,             #course code
    19,             #course unit
    25,             #score + grade
    20,             #quality point
  ];

  /**
   * @var float
   */
  private $studentPhotoWidth = 28.00;

  /**
   * @var float
   */
  private $studentPhotoHeight = 24.00;

  /**
   * @var float
   */
  private $studentPhotoWidthXOffset = .6;

  private $tableTextFont = 11;

  /**
   * The student registration number
   *
   * @var string
   */
  private $regNo;

  /**
   * @constructor
   *
   * @param array $studentScoresData
   */
  public function __construct(array $studentScoresData)
  {
    parent::__construct(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

    $this->_setUpPage();

    $student = $studentScoresData['student'];
    $this->regNo = $student['reg_no'];

    foreach ($studentScoresData['sessions_semesters_courses_grades'] as $session => $semesters) {
      $this->_drawStudentInfo($student);

      foreach ($semesters as $semesterNumber => $semesterDataAndCourses) {

        $this->_drawTableHeader($session, $semesterNumber);
        $this->_drawTableBody($semesterDataAndCourses['courses']);
      }
    }

    $this->Output($this->regNo . '.pdf', 'd');
  }

  private function _setUpPage()
  {
    $this->SetHeaderData(
      PDF_HEADER_LOGO,
      PDF_HEADER_LOGO_WIDTH,
      'Transcript of Academic Records',
      SCHOOL_NAME . "\n" . SCHOOL_ADDRESS . " (" . SCHOOL_WEBSITE . ')',
      [0, 64, 255],
      [0, 64, 128]
    );

    $this->setHeaderFont(['helvetica', '', 14]);

    $this->setFooterData([0, 64, 0], [0, 64, 128]);

    $this->setFooterFont(['helvetica', '', PDF_FONT_SIZE_DATA]);

    $this->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

    $this->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
    $this->SetHeaderMargin(PDF_MARGIN_HEADER);
    $this->SetFooterMargin(PDF_MARGIN_FOOTER);

    $this->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

    $this->setImageScale(PDF_IMAGE_SCALE_RATIO);

    $this->setFontSubsetting(true);

    $this->SetFont('helvetica', '', 8, '', true);

    $this->AddPage();
  }

  /**
   * @param array $studentInfo
   */
  private function _drawStudentInfo(array $studentInfo)
  {
    $columnWidths = [
      'header' => 40,
      'data' => 75,
    ];

    $this->Ln();

    $photo = $studentInfo['photo'];
    $this->Image($photo ? $photo : K_BLANK_IMAGE, '', '', $this->studentPhotoWidth, $this->studentPhotoHeight, '');

    $this->_drawStudentInfoRow('NAME', $studentInfo['names'], 0, $columnWidths, 'T');
    $this->_drawStudentInfoRow('REGISTRATION NO', $studentInfo['reg_no'], 1, $columnWidths);
    $this->_drawStudentInfoRow('DEPARTMENT', $studentInfo['dept_name'], 0, $columnWidths);
    $this->_drawStudentInfoRow('YEAR OF ADMISSION', $studentInfo['academic_year'], 1, $columnWidths);

    $this->_setStudentInfoXOffset();
    $this->Cell(array_sum($columnWidths), 0, '', 'T');
    $this->Ln(5);
  }

  /**
   * Draw a row of student information
   *
   * @param string $header - the row header for the student information
   * @param string $data - the student information
   *
   * @param string $fill - whether the row will be filled or not
   * this affects only student data as the header is always filled
   *
   * @param array $columnWidths
   *
   * @param string $topBorder - whether to draw top border for student
   * information (headers always have top border) - will not be drawn by default
   */
  private function _drawStudentInfoRow($header, $data, $fill, array $columnWidths, $topBorder = '')
  {

    $this->SetFont('', '', 10);
    $this->SetFillColor(224, 235, 255);
    $this->_setStudentInfoXOffset();

    //draw header
    $this->SetTextColor(0);
    $this->SetDrawColor(128, 0, 0);
    $this->SetLineWidth(0.001);
    $this->Cell($columnWidths['header'], 6, $header, 'LT', 0, 'L', 1);


    //draw student information
    $this->SetTextColor(0);
    $this->Cell($columnWidths['data'], 6, $data, 'LR' . $topBorder, 0, 'L', $fill);

    $this->Ln();
  }

  private function _setStudentInfoXOffset()
  {
    $this->SetX($this->GetX() + $this->studentPhotoWidth + $this->studentPhotoWidthXOffset);
  }

  /**
   * @param string $session - the academic session code e.g 2014/2015
   * @param string|int $semesterNumber - the semester number, 1 or 2
   */
  private function _drawTableHeader($session, $semesterNumber)
  {
    $levelDeptForSession = StudentProfile::getCurrentForSession($this->regNo, $session);

    $semesterText = $semesterNumber == 1 ? "FIRST SEMESTER - {$levelDeptForSession['level']} ({$session})" : 'SECOND SEMESTER';

    $this->SetFillColor(200, 219, 255);
    $this->SetTextColor(0);
    $this->SetDrawColor(128, 0, 0);
    $this->SetLineWidth(0.1);
    $this->SetFont('helvetica', 'B', $this->tableTextFont, '', true);

    $this->cell(array_sum($this->coursesScoresCellWidths), '', $semesterText, '', 1, 'C');

    $headers = [
      'S/NO.',
      'COURSE TITLE',
      'COURSE CODE',
      'CREDIT UNIT',
      'GRADE OBTAINED',
      "QUALITY POINT",
    ];

    $numHeaders = count($this->coursesScoresCellWidths);

    for ($index = 0; $index < $numHeaders; $index++) {

      $this->MultiCell(
        $this->coursesScoresCellWidths[$index], //width
        10,                                     //height
        $headers[$index],                       //text
        1,                                      //border
        'C',                                    //align
        1,                                      //fill
        0                                       //next line
      );
    }

    $this->Ln();
  }

  /**
   * Draw table body with student results
   *
   * @param array $coursesScores
   */
  private function _drawTableBody(array $coursesScores)
  {
    $rowHeightSingle = 6;
    $rowHeightDouble = 12;
    $border = 'LRTB';
    $nextPos = 0;
    $maxLenCharsPerLine = 45;

    $this->SetFillColor(224, 235, 255);
    $this->SetTextColor(0);
    $this->SetFont('');

    $fill = 0;
    $seq = 1;

    foreach ($coursesScores as $course) {
      $title = $course['title'];
      $unit = number_format($course['unit'], 1);
      $point = number_format(floatval($unit) * $course['point'], 2);

      if (strlen($title) <= $maxLenCharsPerLine) {
        $this->Cell($this->coursesScoresCellWidths[0], $rowHeightSingle, $seq++, 'LTB', $nextPos, 'R', $fill);
        $this->Cell($this->coursesScoresCellWidths[1], $rowHeightSingle, $title, $border, $nextPos, 'L', $fill);
        $this->Cell($this->coursesScoresCellWidths[2], $rowHeightSingle, $course['code'], $border, $nextPos, 'L', $fill);
        $this->Cell($this->coursesScoresCellWidths[3], $rowHeightSingle, $unit, $border, $nextPos, 'C', $fill);
        $this->Cell($this->coursesScoresCellWidths[4], $rowHeightSingle, $course['score'] . ' ' . $course['grade'], $border, $nextPos, 'R', $fill);
        $this->Cell($this->coursesScoresCellWidths[5], $rowHeightSingle, $point, $border, $nextPos, 'C', $fill);

      } else {
        $this->MultiCell($this->coursesScoresCellWidths[0], $rowHeightDouble, $seq++, 'LTB', 'R', $fill, $nextPos);
        $this->MultiCell($this->coursesScoresCellWidths[1], $rowHeightDouble, $title, $border, 'L', $fill, $nextPos);
        $this->MultiCell($this->coursesScoresCellWidths[2], $rowHeightDouble, $course['code'], $border, 'L', $fill, $nextPos);
        $this->MultiCell($this->coursesScoresCellWidths[3], $rowHeightDouble, $unit, $border, 'C', $fill, $nextPos);
        $this->MultiCell($this->coursesScoresCellWidths[4], $rowHeightDouble, $course['score'] . ' ' . $course['grade'], $border, 'R', $fill, $nextPos);
        $this->MultiCell($this->coursesScoresCellWidths[5], $rowHeightDouble, $point, $border, 'C', $fill, $nextPos);
      }

      $this->Ln();
      $fill = !$fill;
    }

    $this->Ln(10);
  }
}
