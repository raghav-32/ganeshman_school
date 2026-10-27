package np.edu.ganeshman.sms.ui.nav

import android.Manifest
import android.os.Build
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.layout.*
import androidx.compose.ui.platform.LocalContext
import np.edu.ganeshman.sms.notify.NoticeAlerts
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import androidx.navigation.NavHostController
import androidx.navigation.NavType
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.currentBackStackEntryAsState
import androidx.navigation.compose.rememberNavController
import androidx.navigation.navArgument
import np.edu.ganeshman.sms.ui.auth.LoginScreen
import np.edu.ganeshman.sms.ui.auth.PendingScreen
import np.edu.ganeshman.sms.ui.auth.RegisterScreen
import np.edu.ganeshman.sms.ui.common.LocalRepo
import np.edu.ganeshman.sms.ui.common.LocalSettings
import np.edu.ganeshman.sms.ui.common.Routes
import np.edu.ganeshman.sms.ui.components.*
import np.edu.ganeshman.sms.ui.student.*
import np.edu.ganeshman.sms.ui.teacher.*
import np.edu.ganeshman.sms.ui.common.NoticesScreen
import np.edu.ganeshman.sms.ui.common.SettingsScreen
import np.edu.ganeshman.sms.ui.common.AboutScreen
import np.edu.ganeshman.sms.ui.common.SearchScreen
import np.edu.ganeshman.sms.ui.common.ProfileScreen

private val studentTabs = listOf(
    NavItem(Routes.S_HOME, "Home", Icons.Outlined.Home),
    NavItem(Routes.S_TIMETABLE, "Timetable", Icons.Outlined.Schedule),
    NavItem(Routes.S_ATT, "Attendance", Icons.Outlined.EventAvailable),
    NavItem(Routes.S_MORE, "More", Icons.Outlined.Apps),
)
private val teacherTabs = listOf(
    NavItem(Routes.T_HOME, "Home", Icons.Outlined.Home),
    NavItem(Routes.T_ATT, "Attendance", Icons.Outlined.EventAvailable),
    NavItem(Routes.T_MARKS, "Marks", Icons.Outlined.EditNote),
    NavItem(Routes.T_MORE, "More", Icons.Outlined.Apps),
)

/** Which tab lights up for a given route (sub-screens belong to "More"). */
private fun tabFor(route: String?, student: Boolean): String {
    val tabs = if (student) studentTabs else teacherTabs
    val more = tabs.last().route
    if (route == null) return tabs.first().route
    return when {
        tabs.any { it.route == route } -> route
        route == Routes.T_ENTRY -> Routes.T_MARKS
        else -> more
    }
}

private val authRoutes = setOf(Routes.LOGIN, Routes.REGISTER, Routes.PENDING)

@Composable
fun AppRoot() {
    val repo = LocalRepo.current
    val session by repo.session.data.collectAsState()
    val nav = rememberNavController()
    val fabHost = remember { FabHost() }

    // Background notice checks + notification permission (Android 13+)
    val ctx = LocalContext.current
    val permission = rememberLauncherForActivityResult(ActivityResultContracts.RequestPermission()) { }
    val alertsOn by LocalSettings.current.notifications.collectAsState()
    LaunchedEffect(session != null, alertsOn) {
        if (session != null && alertsOn) {
            NoticeAlerts.schedule(ctx)
            if (Build.VERSION.SDK_INT >= 33 && !NoticeAlerts.canNotify(ctx)) {
                permission.launch(Manifest.permission.POST_NOTIFICATIONS)
            }
        } else {
            NoticeAlerts.cancel(ctx)
        }
    }

    val start = remember {
        when {
            repo.session.data.value == null -> Routes.LOGIN
            repo.session.data.value!!.isStudent -> Routes.S_HOME
            else -> Routes.T_HOME
        }
    }

    // Token expired / logged out anywhere -> back to the login screen.
    val entry by nav.currentBackStackEntryAsState()
    val route = entry?.destination?.route
    LaunchedEffect(session) {
        if (session == null) {
            val r = nav.currentDestination?.route
            if (r != null && r !in authRoutes) {
                nav.navigate(Routes.LOGIN) { popUpTo(nav.graph.id) { inclusive = true } }
            }
        }
    }

    val openProfile: (() -> Unit)? = session?.let { sd ->
        { nav.navigate(if (sd.isStudent) Routes.S_PROFILE else Routes.T_PROFILE) { launchSingleTop = true } }
    }

    val openNotices: (() -> Unit)? = session?.let { sd ->
        { nav.navigate(if (sd.isStudent) Routes.S_NOTICES else Routes.T_NOTICES) { launchSingleTop = true } }
    }

    val openSettings: (() -> Unit)? = session?.let { { nav.navigate(Routes.SETTINGS) { launchSingleTop = true } } }
    val openSearch: (() -> Unit)? = session?.let { { nav.navigate(Routes.SEARCH) { launchSingleTop = true } } }

    CompositionLocalProvider(
        LocalFabHost provides fabHost, LocalOpenProfile provides openProfile,
        LocalOpenNotices provides openNotices, LocalOpenSearch provides openSearch,
        LocalOpenSettings provides openSettings,
    ) {
        Box(Modifier.fillMaxSize()) {
            NavHost(nav, startDestination = start) {
                composable(Routes.LOGIN) {
                    LoginScreen(
                        onLoggedIn = { student ->
                            nav.navigate(if (student) Routes.S_HOME else Routes.T_HOME) {
                                popUpTo(nav.graph.id) { inclusive = true }
                            }
                        },
                        onRegister = { nav.navigate(Routes.REGISTER) },
                    )
                }
                composable(Routes.REGISTER) {
                    RegisterScreen(
                        onBack = { nav.popBackStack() },
                        onDone = { nav.navigate(Routes.PENDING) { popUpTo(Routes.LOGIN) } },
                    )
                }
                composable(Routes.PENDING) {
                    PendingScreen(onBackToLogin = {
                        nav.navigate(Routes.LOGIN) { popUpTo(nav.graph.id) { inclusive = true } }
                    })
                }

                // ---------------- student ----------------
                composable(Routes.S_HOME) { StudentHome(nav) }
                composable(Routes.S_ATT) { StudentAttendanceScreen() }
                composable(Routes.S_RESULTS) { ResultsScreen(onOpen = { nav.navigate(Routes.result(it)) }, onBack = { nav.popBackStack() }) }
                composable(Routes.S_MORE) { StudentMore(nav) }
                composable(
                    Routes.S_RESULT,
                    arguments = listOf(navArgument("id") { type = NavType.IntType }),
                ) { e -> ResultDetailScreen(e.arguments?.getInt("id") ?: 0, onBack = { nav.popBackStack() }) }
                composable(Routes.S_TIMETABLE) { TimetableScreen() }
                composable(Routes.S_EXAMS) { ExamsScreen(onBack = { nav.popBackStack() }) }
                composable(Routes.S_FEES) { FeesScreen(onBack = { nav.popBackStack() }) }
                composable(Routes.S_LIBRARY) { LibraryScreen(onBack = { nav.popBackStack() }) }
                composable(Routes.S_NOTICES) { NoticesScreen(student = true, onBack = { nav.popBackStack() }) }
                composable(Routes.S_PROFILE) { ProfileScreen(onBack = { nav.popBackStack() }, onSettings = { nav.navigate(Routes.SETTINGS) }) }

                // ---------------- teacher ----------------
                composable(Routes.T_HOME) { TeacherHome(nav) }
                composable(Routes.T_ATT) { TeacherAttendanceScreen() }
                composable(Routes.T_MARKS) { MarksListScreen(onOpen = { nav.navigate(Routes.entry(it)) }) }
                composable(Routes.T_MORE) { TeacherMore(nav) }
                composable(
                    Routes.T_ENTRY,
                    arguments = listOf(navArgument("examId") { type = NavType.IntType }),
                ) { e -> MarksEntryScreen(e.arguments?.getInt("examId") ?: 0, onBack = { nav.popBackStack() }) }
                composable(Routes.T_STUDENTS) { StudentsScreen(onBack = { nav.popBackStack() }) }
                composable(Routes.T_NOTICES) { NoticesScreen(student = false, onBack = { nav.popBackStack() }) }
                composable(Routes.T_PROFILE) { ProfileScreen(onBack = { nav.popBackStack() }, onSettings = { nav.navigate(Routes.SETTINGS) }) }
                composable(Routes.SETTINGS) {
                    SettingsScreen(onBack = { nav.popBackStack() }, onAbout = { nav.navigate(Routes.ABOUT) }, onSignOut = { })
                }
                composable(Routes.ABOUT) { AboutScreen(onBack = { nav.popBackStack() }) }
                composable(Routes.SEARCH) {
                    SearchScreen(student = session?.isStudent != false, nav = nav, onBack = { nav.popBackStack() })
                }
            }

            val s = session
            if (s != null && route != null && route !in authRoutes) {
                val tabs = if (s.isStudent) studentTabs else teacherTabs
                val spec = fabHost.spec
                // Navigation pill bottom-left; the round plus button sits on the same row, bottom-right.
                Row(
                    Modifier
                        .align(Alignment.BottomCenter)
                        .fillMaxWidth()
                        .navigationBarsPadding()
                        .padding(start = 14.dp, end = 18.dp, bottom = 12.dp),
                    verticalAlignment = Alignment.CenterVertically,
                ) {
                    PillNav(
                        tabs, tabFor(route, s.isStudent),
                        onSelect = { target -> goTab(nav, target, tabs.first().route) },
                    )
                    Spacer(Modifier.weight(1f))
                    if (spec != null && spec.label == null) AppFab(spec)
                }
                // Wide "Save" buttons (attendance / marks) don't fit beside the nav: float them just above it.
                if (spec != null && spec.label != null) {
                    AppFab(
                        spec,
                        Modifier
                            .align(Alignment.BottomEnd)
                            .navigationBarsPadding()
                            .padding(end = 18.dp, bottom = 88.dp),
                    )
                }
            }
        }
    }
}

private fun goTab(nav: NavHostController, target: String, home: String) {
    nav.navigate(target) {
        popUpTo(home) { saveState = true }
        launchSingleTop = true
        restoreState = true
    }
}
