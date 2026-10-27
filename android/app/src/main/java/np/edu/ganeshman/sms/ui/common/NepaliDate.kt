package np.edu.ganeshman.sms.ui.common

import java.time.LocalDate
import java.time.temporal.ChronoUnit

/** Bikram Sambat <-> AD conversion. Same month-length table and 1943-04-14 = BS 2000-01-01 reference as the website. */
object NepaliDate {
    const val START_YEAR = 2000
    private val REF: LocalDate = LocalDate.of(1943, 4, 14)

    val monthNames = listOf("Baisakh", "Jestha", "Ashadh", "Shrawan", "Bhadra", "Ashwin",
        "Kartik", "Mangsir", "Poush", "Magh", "Falgun", "Chaitra")

    private val data: Array<IntArray> = arrayOf(
        intArrayOf(30,32,31,32,31,30,30,30,29,30,29,31),
        intArrayOf(31,31,32,31,31,31,30,29,30,29,30,30),
        intArrayOf(31,31,32,32,31,30,30,29,30,29,30,30),
        intArrayOf(31,32,31,32,31,30,30,30,29,29,30,31),
        intArrayOf(30,32,31,32,31,30,30,30,29,30,29,31),
        intArrayOf(31,31,32,31,31,31,30,29,30,29,30,30),
        intArrayOf(31,31,32,32,31,30,30,29,30,29,30,30),
        intArrayOf(31,32,31,32,31,30,30,30,29,29,30,31),
        intArrayOf(31,31,31,32,31,31,29,30,30,29,29,31),
        intArrayOf(31,31,32,31,31,31,30,29,30,29,30,30),
        intArrayOf(31,31,32,32,31,30,30,29,30,29,30,30),
        intArrayOf(31,32,31,32,31,30,30,30,29,29,30,31),
        intArrayOf(31,31,31,32,31,31,29,30,30,29,30,30),
        intArrayOf(31,31,32,31,31,31,30,29,30,29,30,30),
        intArrayOf(31,31,32,32,31,30,30,29,30,29,30,30),
        intArrayOf(31,32,31,32,31,30,30,30,29,29,30,31),
        intArrayOf(31,31,31,32,31,31,29,30,30,29,30,30),
        intArrayOf(31,31,32,31,31,31,30,29,30,29,30,30),
        intArrayOf(31,32,31,32,31,30,30,29,30,29,30,30),
        intArrayOf(31,32,31,32,31,30,30,30,29,30,29,31),
        intArrayOf(31,31,31,32,31,31,30,29,30,29,30,30),
        intArrayOf(31,31,32,31,31,31,30,29,30,29,30,30),
        intArrayOf(31,32,31,32,31,30,30,30,29,29,30,30),
        intArrayOf(31,32,31,32,31,30,30,30,29,30,29,31),
        intArrayOf(31,31,31,32,31,31,30,29,30,29,30,30),
        intArrayOf(31,31,32,31,31,31,30,29,30,29,30,30),
        intArrayOf(31,32,31,32,31,30,30,30,29,29,30,31),
        intArrayOf(30,32,31,32,31,30,30,30,29,30,29,31),
        intArrayOf(31,31,32,31,31,31,30,29,30,29,30,30),
        intArrayOf(31,31,32,31,32,30,30,29,30,29,30,30),
        intArrayOf(31,32,31,32,31,30,30,30,29,29,30,31),
        intArrayOf(30,32,31,32,31,30,30,30,29,30,29,31),
        intArrayOf(31,31,32,31,31,31,30,29,30,29,30,30),
        intArrayOf(31,31,32,32,31,30,30,29,30,29,30,30),
        intArrayOf(31,32,31,32,31,30,30,30,29,29,30,31),
        intArrayOf(30,32,31,32,31,31,29,30,30,29,29,31),
        intArrayOf(31,31,32,31,31,31,30,29,30,29,30,30),
        intArrayOf(31,31,32,32,31,30,30,29,30,29,30,30),
        intArrayOf(31,32,31,32,31,30,30,30,29,29,30,31),
        intArrayOf(31,31,31,32,31,31,29,30,30,29,30,30),
        intArrayOf(31,31,32,31,31,31,30,29,30,29,30,30),
        intArrayOf(31,31,32,32,31,30,30,29,30,29,30,30),
        intArrayOf(31,32,31,32,31,30,30,30,29,29,30,31),
        intArrayOf(31,31,31,32,31,31,29,30,30,29,30,30),
        intArrayOf(31,31,32,31,31,31,30,29,30,29,30,30),
        intArrayOf(31,32,31,32,31,30,30,29,30,29,30,30),
        intArrayOf(31,32,31,32,31,30,30,30,29,29,30,31),
        intArrayOf(31,31,31,32,31,31,30,29,30,29,30,30),
        intArrayOf(31,31,32,31,31,31,30,29,30,29,30,30),
        intArrayOf(31,32,31,32,31,30,30,30,29,29,30,30),
        intArrayOf(31,32,31,32,31,30,30,30,29,30,29,31),
        intArrayOf(31,31,31,32,31,31,30,29,30,29,30,30),
        intArrayOf(31,31,32,31,31,31,30,29,30,29,30,30),
        intArrayOf(31,32,31,32,31,30,30,30,29,29,30,30),
        intArrayOf(31,32,31,32,31,30,30,30,29,30,29,31),
        intArrayOf(31,31,32,31,31,31,30,29,30,29,30,30),
        intArrayOf(31,31,32,31,32,30,30,29,30,29,30,30),
        intArrayOf(31,32,31,32,31,30,30,30,29,29,30,31),
        intArrayOf(30,32,31,32,31,30,30,30,29,30,29,31),
        intArrayOf(31,31,32,31,31,31,30,29,30,29,30,30),
        intArrayOf(31,31,32,32,31,30,30,29,30,29,30,30),
        intArrayOf(31,32,31,32,31,30,30,30,29,29,30,31),
        intArrayOf(30,32,31,32,31,31,29,30,29,30,29,31),
        intArrayOf(31,31,32,31,31,31,30,29,30,29,30,30),
        intArrayOf(31,31,32,32,31,30,30,29,30,29,30,30),
        intArrayOf(31,32,31,32,31,30,30,30,29,29,30,31),
        intArrayOf(31,31,31,32,31,31,29,30,30,29,29,31),
        intArrayOf(31,31,32,31,31,31,30,29,30,29,30,30),
        intArrayOf(31,31,32,32,31,30,30,29,30,29,30,30),
        intArrayOf(31,32,31,32,31,30,30,30,29,29,30,31),
        intArrayOf(31,31,31,32,31,31,29,30,30,29,30,30),
        intArrayOf(31,31,32,31,31,31,30,29,30,29,30,30),
        intArrayOf(31,32,31,32,31,30,30,29,30,29,30,30),
        intArrayOf(31,32,31,32,31,30,30,30,29,29,30,31),
        intArrayOf(31,31,31,32,31,31,30,29,30,29,30,30),
        intArrayOf(31,31,32,31,31,31,30,29,30,29,30,30),
        intArrayOf(31,32,31,32,31,30,30,30,29,29,30,30),
        intArrayOf(31,32,31,32,31,30,30,30,29,30,29,31),
        intArrayOf(31,31,31,32,31,31,30,29,30,29,30,30),
        intArrayOf(31,31,32,31,31,31,30,29,30,29,30,30),
        intArrayOf(31,32,31,32,31,30,30,30,29,29,30,30),
        intArrayOf(31,31,32,32,31,30,30,30,29,30,30,30),
        intArrayOf(30,32,31,32,31,30,30,30,29,30,30,30),
        intArrayOf(31,31,32,31,31,31,30,29,30,29,30,30),
        intArrayOf(31,31,32,31,31,30,30,30,29,30,30,30),
        intArrayOf(31,32,31,32,30,31,30,30,29,30,30,30),
        intArrayOf(30,32,31,32,31,30,30,30,29,30,30,30),
        intArrayOf(31,31,32,31,31,31,30,30,29,30,30,30),
        intArrayOf(30,31,32,32,30,31,30,30,29,30,30,30),
        intArrayOf(30,32,31,32,31,30,30,30,29,30,30,30),
        intArrayOf(30,32,31,32,31,30,30,30,29,30,30,30)
    )

    val endYear get() = START_YEAR + data.size - 1

    fun daysIn(year: Int, month: Int): Int = data.getOrNull(year - START_YEAR)?.getOrNull(month - 1) ?: 30

    /** AD date -> Triple(year, month, day) in BS, or null when out of range. */
    fun fromAd(ad: LocalDate): Triple<Int, Int, Int>? {
        var rem = ChronoUnit.DAYS.between(REF, ad)
        if (rem < 0) return null
        for (yi in data.indices) for (m in 0 until 12) {
            val d = data[yi][m]
            if (rem < d) return Triple(START_YEAR + yi, m + 1, rem.toInt() + 1)
            rem -= d
        }
        return null
    }

    fun toAd(year: Int, month: Int, day: Int): LocalDate {
        var total = 0L
        for (y in START_YEAR until year) data.getOrNull(y - START_YEAR)?.let { total += it.sum() }
        for (m in 1 until month) total += daysIn(year, m)
        total += day - 1
        return REF.plusDays(total)
    }

    fun format(ad: LocalDate?): String {
        val b = ad?.let { fromAd(it) } ?: return ""
        return "%02d %s %d".format(b.third, monthNames[b.second - 1], b.first)
    }
}
