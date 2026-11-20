package com.equipo.requesty.data

import retrofit2.Response
import retrofit2.http.*

interface RequestyApi {
    @POST("api/login")
    suspend fun login(@Body body: LoginRequest): Response<LoginResponse>

    @GET("api/pedidos")
    suspend fun listarPedidos(): Response<List<Pedido>>

    @POST("api/pedidos")
    suspend fun crearPedido(@Body pedido: Pedido): Response<ApiResponse>

    @DELETE("api/pedidos/{id}")
    suspend fun eliminarPedido(@Path("id") id: Int): Response<ApiResponse>
}
