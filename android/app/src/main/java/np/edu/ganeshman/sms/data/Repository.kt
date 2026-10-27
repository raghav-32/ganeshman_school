package np.edu.ganeshman.sms.data

import kotlinx.serialization.json.JsonArray
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.JsonPrimitive
import kotlinx.serialization.json.buildJsonObject
import kotlinx.serialization.json.put

/** One function per API endpoint. Screens never touch HTTP directly. */
class Repository(val api: ApiClient, val session: Session) {

    // ---- auth ----
    suspend fun login(username: String, password: String, role: String?): LoginData {
        val d: LoginData = api.post("auth/login", buildJsonObject {
            put("username", username)
            put("password", password)
            if (role != null) put("role", role)
        })
        session.save(d.token, d.role, d.profile)
        return d
    }

    suspend fun logout() {
        try { api.post<JsonObject>("auth/logout") } catch (_: Exception) { }
        session.clear()
        api.clearCache()
    }

    suspend fun me(): Profile {
        val d: MeData = api.get("auth/me", fresh = true)
        session.updateProfile(d.profile)
        return d.profile
    }

    suspend fun changePassword(current: String, new: String, confirm: String) {
        api.post<JsonObject>("auth/change_password", buildJsonObject {
            put("current_password", current)
            put("new_password", new)
            put("confirm_password", confirm)
        })
    }

    suspend fun publicClasses(): List<ClassOption> =
        api.get<ClassOptionsData>("auth/classes").classes

    suspend fun register(fields: Map<String, String>, photo: ByteArray?) {
        api.multipart<JsonObject>("auth/register", fields, "photo", "photo.jpg", photo)
    }

    // ---- student ----
    suspend fun studentDashboard(fresh: Boolean = false): StudentDashboard =
        api.get<StudentDashboard>("student/dashboard", fresh = fresh).also { session.markNoticesSeen(it.notices) }
    suspend fun studentAttendance(month: String): StudentAttendance = api.get("student/attendance", mapOf("month" to month))
    suspend fun results(): List<ResultExam> = api.get<ResultsList>("student/results").exams
    suspend fun resultDetail(examId: Int): ResultDetail =
        api.get<ResultDetailData>("student/results", mapOf("exam_id" to examId)).result
    suspend fun studentExams(): List<ExamInfo> = api.get<ExamsData>("student/exams").exams
    suspend fun fees(): FeesData = api.get("student/fees")
    suspend fun timetable(): TimetableData = api.get("student/timetable")
    suspend fun studentNotices(): List<Notice> = noticesRaw(true, false).also { session.markNoticesSeen(it) }
    suspend fun library(q: String): LibraryData = api.get("student/library", mapOf("q" to q))
    suspend fun requestBook(bookId: Int) {
        api.post<JsonObject>("student/library_request", buildJsonObject { put("book_id", bookId) })
    }
    suspend fun cancelRequest(requestId: Int) {
        api.post<JsonObject>("student/library_cancel", buildJsonObject { put("request_id", requestId) })
    }

    // ---- teacher ----
    suspend fun teacherDashboard(fresh: Boolean = false): TeacherDashboard =
        api.get<TeacherDashboard>("teacher/dashboard", fresh = fresh).also { session.markNoticesSeen(it.notices) }
    suspend fun teacherClasses(): List<TeacherClass> = api.get<ClassesData>("teacher/classes").classes
    suspend fun classStudents(classId: Int): StudentsData = api.get("teacher/students", mapOf("class_id" to classId))
    suspend fun teacherAttendance(classId: Int, date: String): TeacherAttendance =
        api.get("teacher/attendance", mapOf("class_id" to classId, "date" to date), fresh = true)
    suspend fun saveAttendance(classId: Int, date: String, statuses: Map<Int, String>): SaveResult =
        api.post("teacher/attendance", buildJsonObject {
            put("class_id", classId)
            put("date", date)
            put("records", JsonArray(statuses.map { (id, st) ->
                buildJsonObject { put("student_id", id); put("status", st) }
            }))
        })
    suspend fun teacherExams(): List<TeacherExam> = api.get<TeacherExamsData>("teacher/exams").exams
    suspend fun marks(examId: Int, subjectId: Int?): MarksData =
        api.get("teacher/marks", mapOf("exam_id" to examId, "subject_id" to subjectId), fresh = true)
    suspend fun saveMarks(examId: Int, subjectId: Int, marks: Map<Int, Double>): SaveResult =
        api.post("teacher/marks", buildJsonObject {
            put("exam_id", examId)
            put("subject_id", subjectId)
            put("marks", JsonArray(marks.map { (id, m) ->
                buildJsonObject { put("student_id", id); put("marks_obtained", JsonPrimitive(m)) }
            }))
        })
    suspend fun teacherNotices(): List<Notice> = noticesRaw(false, false).also { session.markNoticesSeen(it) }

    /** Notices without touching the "seen" marker (used by the background checker). */
    suspend fun noticesRaw(student: Boolean, fresh: Boolean): List<Notice> =
        api.get<NoticesData>(if (student) "student/notices" else "teacher/notices", mapOf("limit" to 50), fresh = fresh).notices
}
