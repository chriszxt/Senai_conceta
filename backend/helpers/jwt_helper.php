<?php

function gerarToken($id_usuario)
{
    return base64_encode($id_usuario . "|" . time());
}