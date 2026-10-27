package np.edu.ganeshman.sms

import android.app.Application
import np.edu.ganeshman.sms.data.ApiClient
import np.edu.ganeshman.sms.data.AppSettings
import np.edu.ganeshman.sms.data.Repository
import np.edu.ganeshman.sms.data.Session

class GmsApp : Application() {
    lateinit var repo: Repository
        private set
    lateinit var settings: AppSettings
        private set

    override fun onCreate() {
        super.onCreate()
        val session = Session(this)
        repo = Repository(ApiClient(session), session)
        settings = AppSettings(this)
    }
}
