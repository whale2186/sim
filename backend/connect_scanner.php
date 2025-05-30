<?php
session_start();
include 'sidebar.php'; // Sidebar for navigation
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Connect Scanner</title>
  <style>
    .container {
      margin-left: 250px;
      padding: 20px;
      font-family: Arial, sans-serif;
    }
    .form-section {
      background: #fff;
      padding: 20px;
      border-radius: 8px;
      box-shadow: 0px 0px 10px rgba(0,0,0,0.1);
      max-width: 500px;
      margin-bottom: 20px;
    }
    label {
      font-weight: bold;
      display: block;
      margin-top: 10px;
    }
    input[type="text"] {
      width: 100%;
      padding: 10px;
      margin: 8px 0;
      border: 1px solid #ddd;
      border-radius: 5px;
    }
    button {
      background: #1e1e2d;
      color: white;
      padding: 10px 15px;
      border: none;
      border-radius: 5px;
      cursor: pointer;
      margin-top: 10px;
    }
    #log {
      margin-top: 20px;
      padding: 10px;
      background: #f9f9f9;
      border: 1px solid #ddd;
      border-radius: 5px;
      min-height: 100px;
      max-width: 500px;
      word-wrap: break-word;
    }
  </style>
</head>
<body>
<div class="container">
  <h1>Connect Scanner</h1>
  <div class="form-section">
    <p>Enter the IP address and Port of the Android device running the Barcode Scanner:</p>
    <label for="ip">IP:</label>
    <input type="text" id="ip" placeholder="172.16.100.24">
    <label for="port">Port:</label>
    <input type="text" id="port" placeholder="9999">
    <button id="connect">Connect</button>
  </div>
  <div id="log"></div>
</div>

<script>
// Log function to display messages on screen
function log(s) {
  var logDiv = document.getElementById('log');
  logDiv.innerHTML += s + "<br />";
}

var TIMEOUT = 5000; // 5 seconds timeout
var wsUri;
var websocket;
var open = false;

function init() {
  // Load saved IP and Port from session storage
  if (sessionStorage.getItem("scanner_ip")) {
    document.getElementById("ip").value = sessionStorage.getItem("scanner_ip");
  }
  if (sessionStorage.getItem("scanner_port")) {
    document.getElementById("port").value = sessionStorage.getItem("scanner_port");
  }

  document.getElementById('connect').addEventListener("click", connect);
}

function connect() {
  var sIP = document.getElementById('ip').value;
  var sPort = document.getElementById('port').value;
  
  if (sIP === "" || sPort === "") {
    log("Please enter IP address and port number.");
  } else {
    wsUri = "ws://" + sIP + ":" + sPort + "/";
    log("Connecting to " + wsUri + "...");
    websocket = new WebSocket(wsUri);
    websocket.onopen = onopen;
    websocket.onerror = onerror;
    websocket.onclose = onclose;
    
    // Avoid endless connection retries
    window.setTimeout(onConnectionTimeout, TIMEOUT);
  }
}

function onConnectionTimeout() {
  if (!open) {
    log("Connection to " + wsUri + " timed out.");
    websocket.close();
  }
}

function onopen() {
  open = true;
  log("Connection to " + wsUri + " opened successfully!");

  // Save IP and Port to session storage
  var sIP = document.getElementById('ip').value;
  var sPort = document.getElementById('port').value;
  sessionStorage.setItem("scanner_ip", sIP);
  sessionStorage.setItem("scanner_port", sPort);
}

function onerror(event) {
  log("Connection error. Please check the IP and Port.");
  console.error("WebSocket error:", event);
}

function onclose(event) {
  log("Connection closed.");
  open = false;
}

window.addEventListener("load", init, false);
</script>
</body>
</html>
