package np.edu.ganeshman.sms.notify

import android.Manifest
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.os.Build
import androidx.core.app.NotificationCompat
import androidx.core.app.NotificationManagerCompat
import androidx.core.content.ContextCompat
import androidx.work.*
import np.edu.ganeshman.sms.GmsApp
import np.edu.ganeshman.sms.MainActivity
import np.edu.ganeshman.sms.R
import np.edu.ganeshman.sms.data.ApiException
import np.edu.ganeshman.sms.data.Notice
import java.util.concurrent.TimeUnit

private const val CHANNEL = "notices"
private const val WORK = "notice-check"

object NoticeAlerts {

    /** Checks for new notices about every 15 minutes while someone is signed in. */
    fun schedule(context: Context) {
        val req = PeriodicWorkRequestBuilder<NoticeWorker>(15, TimeUnit.MINUTES)
            .setConstraints(Constraints.Builder().setRequiredNetworkType(NetworkType.CONNECTED).build())
            .setBackoffCriteria(BackoffPolicy.LINEAR, 5, TimeUnit.MINUTES)
            .build()
        WorkManager.getInstance(context)
            .enqueueUniquePeriodicWork(WORK, ExistingPeriodicWorkPolicy.KEEP, req)
    }

    fun cancel(context: Context) {
        WorkManager.getInstance(context).cancelUniqueWork(WORK)
    }

    fun canNotify(context: Context): Boolean =
        Build.VERSION.SDK_INT < 33 ||
            ContextCompat.checkSelfPermission(context, Manifest.permission.POST_NOTIFICATIONS) == PackageManager.PERMISSION_GRANTED

    fun show(context: Context, notices: List<Notice>) {
        if (notices.isEmpty() || !canNotify(context)) return
        if (!(context.applicationContext as GmsApp).settings.notifications.value) return   // turned off in Settings
        val nm = NotificationManagerCompat.from(context)
        if (Build.VERSION.SDK_INT >= 26) {
            nm.createNotificationChannel(
                NotificationChannel(CHANNEL, "School notices", NotificationManager.IMPORTANCE_HIGH)
                    .apply { description = "New announcements from the school" }
            )
        }
        val open = PendingIntent.getActivity(
            context, 0,
            Intent(context, MainActivity::class.java).addFlags(Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP),
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE,
        )
        try {
            notices.sortedBy { it.id }.takeLast(5).forEach { n ->
                nm.notify(
                    n.id,
                    NotificationCompat.Builder(context, CHANNEL)
                        .setSmallIcon(R.drawable.ic_notification)
                        .setContentTitle(n.title)
                        .setContentText(n.content)
                        .setStyle(NotificationCompat.BigTextStyle().bigText(n.content))
                        .setColor(0xFF6C6FD8.toInt())
                        .setAutoCancel(true)
                        .setContentIntent(open)
                        .setPriority(NotificationCompat.PRIORITY_HIGH)
                        .build()
                )
            }
        } catch (_: SecurityException) { }
    }
}

class NoticeWorker(ctx: Context, params: WorkerParameters) : CoroutineWorker(ctx, params) {
    override suspend fun doWork(): Result {
        val repo = (applicationContext as GmsApp).repo
        val s = repo.session.data.value ?: return Result.success()
        return try {
            val notices = repo.noticesRaw(s.isStudent, fresh = true)
            val last = repo.session.lastNoticeId
            if (last >= 0) {
                NoticeAlerts.show(applicationContext, notices.filter { it.id > last })
            }
            // first run (last == -1) only sets the baseline, no flood of old notices
            repo.session.markNoticesSeen(notices)
            Result.success()
        } catch (e: ApiException) {
            if (e.code == 401) Result.success() else Result.retry()
        } catch (e: Exception) {
            Result.retry()
        }
    }
}
