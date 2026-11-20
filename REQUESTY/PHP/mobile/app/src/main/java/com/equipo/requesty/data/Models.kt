package com.equipo.requesty.data

import com.google.gson.annotations.SerializedName

data class LoginRequest(
    @SerializedName("correo") val correo: String,
    @SerializedName("password") val password: String
)

data class LoginResponse(
    @SerializedName("token") val token: String,
    @SerializedName("nombre") val nombre: String,
    @SerializedName("rol") val rol: String
)

data class Pedido(
    @SerializedName("id") val id: Int? = null,
    @SerializedName("numero_mesa") val numeroMesa: Int,
    @SerializedName("tipo") val tipo: String = "Mesa",
    @SerializedName("estado") val estado: String = "PENDIENTE",
    @SerializedName("total") val total: Double = 0.0
)

data class ApiResponse(
    @SerializedName("mensaje") val mensaje: String? = null,
    @SerializedName("error") val error: String? = null
)
