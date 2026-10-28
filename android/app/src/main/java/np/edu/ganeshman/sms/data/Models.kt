package np.edu.ganeshman.sms.data

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable

// Mirrors the JSON of api/v1. Property names are camelCase here and are mapped to the
// API's snake_case automatically (see ApiClient.json). Everything has a default so a
// missing/null field never crashes the app.

@Serializable
data class ClassRef(
    val id: Int = 0,
    val name: String = "",
    val section: String = "",
    val label: String = "",
)

@Serializable
data class Profile(
    val role: String = "",
    val id: Int = 0,
    val studentCode: String? = null,
    val teacherCode: String? = null,
    val username: String? = null,
    val firstName: String = "",
    val lastName: String = "",
    val fullName: String = "",
    val dob: String? = null,
    val dobBs: String? = null,
    val gender: String? = null,
    val address: String? = null,
    val phone: String? = null,
    val email: String? = null,
    val guardianName: String? = null,
    val guardianPhone: String? = null,
    val guardianRelation: String? = null,
    @SerialName("class") val klass: ClassRef? = null,
    val admissionDate: String? = null,
    val admissionDateBs: String? = null,
    val qualification: String? = null,
    val joiningDate: String? = null,
    val joiningDateBs: String? = null,
    val photoUrl: String? = null,
    val lastLogin: String? = null,
) {
    val isStudent get() = role == "student"
    val code get() = studentCode ?: teacherCode ?: ""
}

@Serializable
data class LoginData(
    val token: String = "",
    val tokenType: String = "Bearer",
    val expiresAt: String = "",
    val role: String = "",
    val profile: Profile = Profile(),
)

@Serializable
data class MeData(val role: String = "", val profile: Profile = Profile())

@Serializable
data class TodayInfo(val date: String = "", val dateBs: String? = null, val weekday: String = "")

@Serializable
data class AttendanceSummary(
    val total: Int = 0,
    val present: Int = 0,
    val absent: Int = 0,
    val late: Int = 0,
    val leave: Int = 0,
    val percentage: Double = 0.0,
)

@Serializable
data class Notice(
    val id: Int = 0,
    val title: String = "",
    val content: String = "",
    val imageUrl: String? = null,
    val audience: String = "",
    val postedBy: String? = null,
    val createdAt: String = "",
    val dateBs: String? = null,
)

@Serializable
data class NoticesData(val notices: List<Notice> = emptyList())

// ---------------- student ----------------

@Serializable
data class ResultExam(
    val examId: Int = 0,
    val examName: String = "",
    val examType: String = "",
    val startDate: String? = null,
    val startDateBs: String? = null,
    @SerialName("class") val klass: ClassRef? = null,
    val totalObtained: Double = 0.0,
    val totalFull: Double = 0.0,
    val percentage: Double = 0.0,
    val subjectCount: Int = 0,
)

@Serializable
data class FeeSummary(
    val totalFee: Double = 0.0,
    val totalPaid: Double = 0.0,
    val totalDue: Double = 0.0,
)

@Serializable
data class IssuedBook(
    val id: Int = 0,
    val bookId: Int = 0,
    val title: String = "",
    val author: String? = null,
    val shelfLocation: String? = null,
    val issueDate: String = "",
    val issueDateBs: String? = null,
    val dueDate: String = "",
    val dueDateBs: String? = null,
    val isOverdue: Boolean = false,
    val daysLeft: Int = 0,
    val overdueDays: Int = 0,
    val estimatedFine: Int = 0,
)

@Serializable
data class StudentLite(
    val id: Int = 0,
    val studentCode: String = "",
    val firstName: String = "",
    val fullName: String = "",
    @SerialName("class") val klass: ClassRef? = null,
    val photoUrl: String? = null,
)

@Serializable
data class StudentDashboard(
    val today: TodayInfo = TodayInfo(),
    val student: StudentLite = StudentLite(),
    val attendance: AttendanceSummary = AttendanceSummary(),
    val latestResults: List<ResultExam> = emptyList(),
    val fees: FeeSummary = FeeSummary(),
    val issuedBooks: List<IssuedBook> = emptyList(),
    val notices: List<Notice> = emptyList(),
)

@Serializable
data class AttendanceRecord(
    val id: Int = 0,
    val date: String = "",
    val dateBs: String? = null,
    val weekday: String = "",
    val status: String = "",
    val remarks: String? = null,
)

@Serializable
data class StudentAttendance(
    val month: String = "",
    val summary: AttendanceSummary = AttendanceSummary(),
    val monthSummary: AttendanceSummary = AttendanceSummary(),
    val records: List<AttendanceRecord> = emptyList(),
)

@Serializable
data class ResultsList(val exams: List<ResultExam> = emptyList())

@Serializable
data class SubjectResult(
    val subjectId: Int = 0,
    val subjectName: String = "",
    val marksObtained: Double = 0.0,
    val fullMarks: Int = 100,
    val passMarks: Int = 40,
    val percentage: Double = 0.0,
    val grade: String = "",
    val passed: Boolean = false,
    val remarks: String? = null,
)

@Serializable
data class ResultDetail(
    val examId: Int = 0,
    val examName: String = "",
    val examType: String = "",
    val startDateBs: String? = null,
    @SerialName("class") val klass: ClassRef? = null,
    val subjects: List<SubjectResult> = emptyList(),
    val totalObtained: Double = 0.0,
    val totalFull: Double = 0.0,
    val percentage: Double = 0.0,
    val grade: String = "",
    val passed: Boolean = false,
)

@Serializable
data class ResultDetailData(val result: ResultDetail = ResultDetail())

@Serializable
data class ExamScheduleRow(
    val subjectId: Int = 0,
    val subjectName: String = "",
    val date: String = "",
    val dateBs: String? = null,
    val weekday: String = "",
    val startTime: String? = null,
    val endTime: String? = null,
    val venue: String? = null,
    val notes: String? = null,
    val isToday: Boolean = false,
)

@Serializable
data class ExamInfo(
    val id: Int = 0,
    val examName: String = "",
    val examType: String = "",
    @SerialName("class") val klass: ClassRef? = null,
    val startDate: String? = null,
    val startDateBs: String? = null,
    val endDate: String? = null,
    val endDateBs: String? = null,
    val status: String = "",
    val resultsPublished: Boolean = false,
    val schedule: List<ExamScheduleRow> = emptyList(),
)

@Serializable
data class ExamsData(val exams: List<ExamInfo> = emptyList())

@Serializable
data class FeeItem(
    val feeStructureId: Int = 0,
    val feeType: String = "",
    val academicYear: String? = null,
    val dueDayOfMonth: Int = 0,
    val amount: Double = 0.0,
    val paid: Double = 0.0,
    val due: Double = 0.0,
    val percentage: Int = 0,
    val status: String = "",
)

@Serializable
data class Payment(
    val id: Int = 0,
    val receiptNumber: String? = null,
    val feeType: String = "",
    val amountPaid: Double = 0.0,
    val paymentDate: String = "",
    val paymentDateBs: String? = null,
    val paymentMethod: String = "",
    val isPartial: Boolean = false,
    val remarks: String? = null,
)

@Serializable
data class FeesData(
    val summary: FeeSummary = FeeSummary(),
    val fees: List<FeeItem> = emptyList(),
    val payments: List<Payment> = emptyList(),
)

@Serializable
data class Period(
    val id: Int = 0,
    val subjectId: Int = 0,
    val subjectName: String = "",
    val teacherName: String = "",
    val startTime: String? = null,
    val endTime: String? = null,
    val roomNumber: String? = null,
)

@Serializable
data class DaySchedule(
    val day: String = "",
    val isToday: Boolean = false,
    val periods: List<Period> = emptyList(),
)

@Serializable
data class TimetableData(val today: String = "", val days: List<DaySchedule> = emptyList())

@Serializable
data class Book(
    val id: Int = 0,
    val title: String = "",
    val author: String? = null,
    val category: String? = null,
    val publisher: String? = null,
    val publishYear: Int? = null,
    val shelfLocation: String? = null,
    val totalCopies: Int = 0,
    val availableCopies: Int = 0,
    val available: Boolean = false,
    val requested: Boolean = false,
)

@Serializable
data class BookRequest(
    val id: Int = 0,
    val bookId: Int = 0,
    val title: String = "",
    val author: String? = null,
    val available: Boolean = false,
    val requestDate: String = "",
    val requestDateBs: String? = null,
)

@Serializable
data class LibraryData(
    val books: List<Book> = emptyList(),
    val myIssued: List<IssuedBook> = emptyList(),
    val myRequests: List<BookRequest> = emptyList(),
    val limit: Int = 50,
    val offset: Int = 0,
)

// ---------------- teacher ----------------

@Serializable
data class TeacherClass(
    val id: Int = 0,
    val name: String = "",
    val section: String = "",
    val label: String = "",
    val isClassTeacher: Boolean = false,
    val studentCount: Int = 0,
)

@Serializable
data class TeacherDashboard(
    val today: TodayInfo = TodayInfo(),
    val teacher: Profile = Profile(),
    val classes: List<TeacherClass> = emptyList(),
    val classesMarkedToday: Int = 0,
    val notices: List<Notice> = emptyList(),
)

@Serializable
data class ClassesData(val classes: List<TeacherClass> = emptyList())

@Serializable
data class AttStudent(
    val id: Int = 0,
    val studentCode: String = "",
    val fullName: String = "",
    val photoUrl: String? = null,
    val status: String? = null,
)

@Serializable
data class TeacherAttSummary(
    val present: Int = 0,
    val absent: Int = 0,
    val late: Int = 0,
    val leave: Int = 0,
    val unmarked: Int = 0,
)

@Serializable
data class TeacherAttendance(
    @SerialName("class") val klass: TeacherClass = TeacherClass(),
    val date: String = "",
    val dateBs: String? = null,
    val canMark: Boolean = false,
    val summary: TeacherAttSummary = TeacherAttSummary(),
    val students: List<AttStudent> = emptyList(),
)

@Serializable
data class SaveResult(val saved: Int = 0)

@Serializable
data class TeacherExam(
    val id: Int = 0,
    val examName: String = "",
    val examType: String = "",
    @SerialName("class") val klass: ClassRef = ClassRef(),
    val startDate: String? = null,
    val startDateBs: String? = null,
    val endDateBs: String? = null,
    val isPublished: Boolean = false,
    val resultsPublished: Boolean = false,
    val canEnterMarks: Boolean = false,
)

@Serializable
data class TeacherExamsData(val exams: List<TeacherExam> = emptyList())

@Serializable
data class SubjectInfo(
    val id: Int = 0,
    val subjectName: String = "",
    val subjectCode: String? = null,
    val fullMarks: Int = 100,
    val passMarks: Int = 40,
)

@Serializable
data class MarkStudent(
    val id: Int = 0,
    val studentCode: String = "",
    val fullName: String = "",
    val marksObtained: Double? = null,
    val grade: String? = null,
)

@Serializable
data class MarksExam(
    val id: Int = 0,
    val examName: String = "",
    val examType: String = "",
    @SerialName("class") val klass: TeacherClass = TeacherClass(),
    val startDateBs: String? = null,
)

@Serializable
data class MarksData(
    val exam: MarksExam = MarksExam(),
    val canEnterMarks: Boolean = false,
    val subjects: List<SubjectInfo> = emptyList(),
    val subject: SubjectInfo? = null,
    val students: List<MarkStudent> = emptyList(),
)

@Serializable
data class ClassStudent(
    val id: Int = 0,
    val studentCode: String = "",
    val firstName: String = "",
    val lastName: String = "",
    val fullName: String = "",
    val gender: String? = null,
    val dobBs: String? = null,
    val phone: String? = null,
    val photoUrl: String? = null,
    val presentDays: Int = 0,
    val totalDays: Int = 0,
    val attendancePercentage: Double = 0.0,
)

@Serializable
data class StudentsData(
    @SerialName("class") val klass: TeacherClass = TeacherClass(),
    val students: List<ClassStudent> = emptyList(),
)

// ---------------- public ----------------

@Serializable
data class ClassOption(
    val id: Int = 0,
    val name: String = "",
    val section: String = "",
    val label: String = "",
)

@Serializable
data class ClassOptionsData(val classes: List<ClassOption> = emptyList())
