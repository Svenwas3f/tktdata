<?php
//Start session
session_start();

//Require files
require_once( dirname(__FILE__) . "/general.php");

/**
 * Check if user is already logged in
 */
if((isset($_SESSION["login"]) && $_SESSION["login"]) && (isset($_SESSION["user"]) && !empty($_SESSION["user"]))) {
  header("Location: " . $url); //Redirect to first accessable page
}

/**
 * Reset password and send mail with new password
 */
if(! empty($_POST)) {
  //Get infos of user by id or mail
  $resetUser = User::authorizeId( $_POST["id"] );

  //Check user
  if($resetUser === false) {
    //User does not exist
    Action::fail( Language::string( 11, null, "auth" ) );
  }else{
    //Reset password
    $newPassword = User::resetPassword( $resetUser["id"]);

    //Check if new password is set correctly
    if(! $newPassword) {
      Action::fail( Language::string( 12, null, "auth" ) );
    }

    // Mail message
    $msg = Language::string( 0, array(
      "%user%" => $resetUser["name"],
      "%url%" => $url,
      "%userid%" => $resetUser["id"],
      "%new_password%" => $newPassword,
    ), "email" );

    $mail = new TKTDataMailer();
    $mail->CharSet = "UTF-8";
    $mail->setFrom(EMAIL,  Language::string( 1, null, "email" ));
    $mail->addAddress($resetUser["email"]);
    $mail->Subject = Language::string( 2, null, "email" );
    $mail->msgHTML( $mail->htmlMail_TktdataMail( $msg ) );

    if($mail->send()) {
      Action::success( Language::string( 13, null, "auth" ) );
    }else {
      Action::fail( Language::string( 14, null, "auth" ) );
    }
  }
}

?>
<!DOCTYPE html>
<html lang="de" dir="ltr">
  <head>
    <meta charset="utf-8">
    <title><?php echo Language::string( 0, null, "general" ); ?></title>

    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="cache-control" content="no-cache">

    <meta name="author" content="Sven Waser">
    <meta name="publisher" content="Sven Waser">
    <meta name="copyright" content="Sven Waser">
    <meta name="reply-to" content="sven.waser@sven-waser.ch">

    <meta name="description" content="<?php echo Language::string( 1, null, "general" ); ?>">
    <meta name="keywords" content="<?php echo Language::string( 2, null, "general" ); ?>">

    <meta name="content-language" content="de">
    <meta name="robots" content="noindex">

    <meta name="theme-color" content="#232b43">


    <link rel="shortcut icon" type="image/x-icon" href="<?php echo $url; ?>medias/logo/favicon.ico">
    <link rel="shortcut icon" href="<?php echo $url; ?>medias/logo/favicon.ico">
    <link rel="icon" type="image/png" href="<?php echo $url; ?>medias/logo/favicon.png" sizes="32x32">
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo $url; ?>medias/logo/logo-512.png">
    <link rel="apple-touch-icon-precomposed" sizes="180x180" href="<?php echo $url; ?>medias/logo/logo-512.png">
    <meta name="msapplication-TileColor" content="#232b43">
    <meta name="msapplication-TileImage" content="<?php echo $url; ?>medias/logo/logo-512.png">

    <!-- Custom scripts -->
    <link rel="stylesheet" href="<?php echo $url; ?>style.css" />
    <link rel="stylesheet" href="<?php echo $url; ?>fonts/fonts.css" />
  </head>
  <body class="auth-background">

    <div class="auth-form">

      <?php
      $form = new HTML('form', array(
        'action' => $url . "reset.php",
        'method' => 'post',
      ),);

      $form->customHTML('<a href="' . $url . '"><img src="' . $url . 'medias/logo/logo-fitted.png"></a>');


      $form->addElement(
        array(
          'type' => 'text',
          'name' => 'id',
          'placeholder' => Language::string( 6, null, "auth" ),
          'required' => true,
        ),
      );

      $form->addElement(
        array(
          'type' => 'button',
          'name' => 'reset',
          'value' => Language::string( 7, null, "auth" ),
          'additional' => 'title="' . Language::string( 8, null, "auth" ) . '"',
        ),
      );

      $form->customHTML('<a class="reset-link" href="' . $url . 'auth.php" title="' . Language::string( 9, null, "auth" ) . '">' . Language::string( 10, null, "auth" ) . '</a>');

      $form->prompt();
      ?>
    </div>

    <div class="auth-footer">
      <?php
      /**
       *  Display footer
       */
      footer();
       ?>
    </div>
  </body>
</html>
