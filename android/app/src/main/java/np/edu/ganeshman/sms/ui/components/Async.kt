package np.edu.ganeshman.sms.ui.components

import androidx.compose.foundation.layout.ColumnScope
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.height
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.outlined.CloudOff
import androidx.compose.material3.Text
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import kotlinx.coroutines.CancellationException
import np.edu.ganeshman.sms.data.ApiException

sealed interface Load<out T> {
    data object Loading : Load<Nothing>
    data class Error(val message: String) : Load<Nothing>
    data class Ready<T>(val data: T) : Load<T>
}

class AsyncState<T>(val load: Load<T>, val reload: () -> Unit)

@Suppress("UNCHECKED_CAST")
fun <T> Load<T>.dataOrNull(): T? = (this as? Load.Ready<T>)?.data

/** Runs [block] when the screen appears (and whenever [key] changes or reload() is called). */
@Composable
fun <T> rememberAsync(key: Any? = Unit, block: suspend () -> T): AsyncState<T> {
    var load by remember(key) { mutableStateOf<Load<T>>(Load.Loading) }
    var tick by remember(key) { mutableIntStateOf(0) }
    val api = np.edu.ganeshman.sms.ui.common.LocalRepo.current.api
    val current by rememberUpdatedState(block)
    LaunchedEffect(key, tick) {
        if (load !is Load.Ready<*>) load = Load.Loading
        load = try {
            Load.Ready(current())
        } catch (e: CancellationException) {
            throw e
        } catch (e: ApiException) {
            Load.Error(e.message ?: "Something went wrong.")
        } catch (e: Exception) {
            Load.Error("Something went wrong. Please try again.")
        }
    }
    return AsyncState(load) { api.clearCache(); tick++ }
}

/** Skeletons while loading, a friendly retry card on failure, otherwise [content]. */
@Composable
fun <T> ColumnScope.AsyncContent(
    state: AsyncState<T>,
    skeletons: Int = 3,
    skeletonHeight: androidx.compose.ui.unit.Dp = 96.dp,
    content: @Composable ColumnScope.(T) -> Unit,
) {
    when (val l = state.load) {
        is Load.Loading -> SkeletonList(skeletons, skeletonHeight)
        is Load.Error -> EmptyState(
            illustration = Illustration.Offline,
            title = "Couldn't load",
            message = l.message,
            actionLabel = "Try again",
            onAction = state.reload,
        )
        is Load.Ready -> content(l.data)
    }
}
