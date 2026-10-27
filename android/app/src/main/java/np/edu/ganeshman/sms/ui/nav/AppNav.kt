package np.edu.ganeshman.sms.ui.nav

import androidx.compose.foundation.layout.*
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
import np.edu.ganeshman.sms.ui.common.Routes
import np.edu.ganeshman.sms.ui.components.*
import np.edu.ganeshman.sms.ui.student.*
import np.edu.ganeshman.sms.ui.teacher.*
import np.edu.ganeshman.sms.ui.common.NoticesScreen
import np.edu.ganeshman.sms.ui.common.ProfileScreen

private val studentTabs = listOf(
    NavItem(Routes.S_HOME, "Home", Icons.Outlined.Home),
    NavItem(Routes.S_ATT, "Attendance", Icons.Outlined.EventAvailable),
    NavItem(Routes.S_RESULTS, "Results", Icons.Outlined.EmojiEvents),
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
        route == Routes.S_RESULT -> Routes.S_RESULTS
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

    CompositionLocalProvider(LocalFabHost provides fabHost) {
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
                composable(Routes.S_RESULTS) { ResultsScreen(onOpen = { nav.navigate(Routes.result(it)) }) }
                composable(Routes.S_MORE) { StudentMore(nav) }
                composable(
                    Routes.S_RESULT,
                    arguments = listOf(navArgument("id") { type = NavType.IntType }),
                ) { e -> ResultDetailScreen(e.arguments?.getInt("id") ?: 0, onBack = { nav.popBackStack() }) }
                composable(Routes.S_TIMETABLE) { TimetableScreen(onBack = { nav.popBackStack() }) }
                composable(Routes.S_EXAMS) { ExamsScreen(onBack = { nav.popBackStack() }) }
                composable(Routes.S_FEES) { FeesScreen(onBack = { nav.popBackStack() }) }
                composable(Routes.S_LIBRARY) { LibraryScreen(onBack = { nav.popBackStack() }) }
                composable(Routes.S_NOTICES) { NoticesScreen(student = true, onBack = { nav.popBackStack() }) }
                composable(Routes.S_PROFILE) { ProfileScreen(onBack = { nav.popBackStack() }) }

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
                composable(Routes.T_PROFILE) { ProfileScreen(onBack = { nav.popBackStack() }) }
            }

            val s = session
            if (s != null && route != null && route !in authRoutes) {
                val tabs = if (s.isStudent) studentTabs else teacherTabs
                fabHost.spec?.let { spec ->
                    AppFab(
                        spec,
                        Modifier
                            .align(Alignment.BottomEnd)
                            .navigationBarsPadding()
                            .padding(end = 20.dp, bottom = 92.dp),
                    )
                }
                PillNav(
                    tabs, tabFor(route, s.isStudent),
                    onSelect = { target -> goTab(nav, target, tabs.first().route) },
                    modifier = Modifier
                        .align(Alignment.BottomCenter)
                        .navigationBarsPadding()
                        .padding(bottom = 12.dp),
                )
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
