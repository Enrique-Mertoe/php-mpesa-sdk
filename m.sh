#!/bin/bash

# Main directories
mkdir -p src/{Config,Auth,Services,Utils,Http,Exceptions,Callbacks} \
         tests/{Unit,Integration} \
         examples/callbacks \
         docs

# Root-level files
touch README.md composer.json .env.example .gitignore

# Source files
touch src/MpesaSDK.php
touch src/Config/Config.php
touch src/Auth/TokenManager.php
touch src/Services/{STKPush.php,C2BRegister.php,C2BSimulate.php,B2C.php,B2B.php,AccountBalance.php,TransactionStatus.php,Reversal.php}
touch src/Utils/{SecurityCredential.php,Validator.php,Logger.php}
touch src/Http/{HttpClient.php,Response.php}
touch src/Exceptions/{MpesaException.php,AuthException.php,ValidationException.php,HttpException.php}
touch src/Callbacks/{CallbackHandler.php,STKPushCallback.php,C2BCallback.php,B2CCallback.php}

# Test files
touch tests/Unit/{ConfigTest.php,TokenManagerTest.php,STKPushTest.php,ValidatorTest.php}
touch tests/Integration/{STKPushIntegrationTest.php,B2CIntegrationTest.php}
touch tests/TestCase.php

# Example files
touch examples/{stkpush.php,b2c.php,c2b.php}
touch examples/callbacks/{stkpush_callback.php,c2b_callback.php}

# Docs files
touch docs/{installation.md,configuration.md,services.md,callbacks.md}

echo "✅ mpesa-sdk project structure created!"
