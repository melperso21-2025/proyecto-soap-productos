# Guía: desplegar el servidor SOAP en la nube (Render)

El PDF de la tarea exige que el WSDL responda en `http://localhost:8000/productos?wsdl`
para la entrega y la defensa — **eso siempre debe funcionar en local**, sin
depender de internet. Desplegarlo en la nube es un extra para tener un
ambiente de pruebas accesible por el equipo sin que todos prendan su propio
servidor.

## Por qué Render y no Vercel

La librería `soap` de Node necesita un proceso HTTP que quede escuchando de
forma continua (`server.listen(...)`). Vercel funciona con funciones
serverless: se despiertan por request y se apagan — no hay garantía de que el
mismo proceso (con el WSDL ya cargado y la memoria de productos) siga vivo
entre una llamada y otra. Render (o Railway) sí te da una instancia con un
proceso Node persistente, igual que tu máquina local.

## Pasos en Render (plan gratuito)

1. Sube el proyecto a GitHub primero (ver [`GUIA_GITHUB.md`](GUIA_GITHUB.md)).
2. En [render.com](https://render.com) → **New** → **Web Service**.
3. Conecta tu cuenta de GitHub y selecciona el repo `proyecto-soap-productos`.
4. Configuración del servicio:
   - **Root Directory**: `servidor`
   - **Runtime**: Node
   - **Build Command**: `npm install`
   - **Start Command**: `npm start`
   - **Instance Type**: Free
5. **Environment Variables** (pestaña Environment): agrega `SUPABASE_URL` y
   `SUPABASE_KEY` con los mismos valores de tu `.env` local. `PORT` no hace
   falta definirlo — Render lo inyecta automáticamente y `server.js` ya lee
   `process.env.PORT`.
6. **Create Web Service**. Render construye y despliega; te da una URL como
   `https://proyecto-soap-productos.onrender.com`.
7. Verifica el WSDL en la nube:
   `https://proyecto-soap-productos.onrender.com/productos?wsdl`

## Advertencia del plan gratuito de Render

Los servicios free "duermen" tras ~15 minutos sin tráfico y tardan unos
segundos en despertar con la primera petición. Si van a usarlo en la defensa
virtual en vivo, hagan una petición de calentamiento (`curl` o abrir el WSDL
en el navegador) 1-2 minutos antes de empezar.

## SoapUI contra el WSDL desplegado

En SoapUI pueden crear un segundo proyecto de pruebas apuntando a la URL de
Render en vez de `localhost`, para demostrar que el servicio también funciona
en la nube. No reemplaza las pruebas locales que pide el PDF — es evidencia
adicional.
