package com.equipo.requesty.data

import okhttp3.OkHttpClient
import retrofit2.Retrofit
import retrofit2.converter.gson.GsonConverterFactory

object RetrofitClient {
    // 10.0.2.2 es el host del emulador de Android Studio para conectar a XAMPP
    private const val BASE_URL = "http://10.0.2.2/Requesty%20-%20BETA%20v_0.2%20UPDATE/public/"
    var token: String? = null

    private val client = OkHttpClient.Builder()
        .addInterceptor { chain ->
            val builder = chain.request().newBuilder()
            token?.let {
                builder.addHeader("Authorization", "Bearer $it")
            }
            chain.proceed(builder.build())
        }
        .build()

    val api: RequestyApi by lazy {
        Retrofit.Builder()
            .baseUrl(BASE_URL)
            .client(client)
            .addConverterFactory(GsonConverterFactory.create())
            .build()
            .create(RequestyApi::class.java)
    }
}
