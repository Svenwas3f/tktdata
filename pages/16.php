<?php
$action = $_GET;
unset($action["id"]); //Remove page
unset($action["sub"]); //Remove subpage
unset($action["row-start"]); //Remove tow to get only valid keys
unset($action["pub"]);
unset($action["s"]); //Remove search value

// Start pub
$pub = new Pub();

// Get current pub
$accessable_pubs = $pub->accessable( $current_user );
$pub->pub = $_GET["pub"] ?? $accessable_pubs[0];

// Start transaction
$transaction = new Transaction();
$transaction->pub = $pub->pub;

// Get Access
$write_page = User::w_access_allowed( $page, $current_user );
$write_pub = boolval( $pub->access( $current_user )["w"] ?? 0 );
$write_access = ($write_page === true && $write_pub === true) ?  true : false;

$read_page = User::r_access_allowed( $page, $current_user );
$read_pub = boolval( $write_pub === true ? true : $pub->access( $current_user )["r"] ?? 0 );
$read_access = ($read_page === true && $read_pub === true) ?  true : false;

// Message if user has no access to this pub
if( $write_access === false && $read_access === false ) {
  Action::fs_info(
    Language::string( 70,
      array(
        '%id%' => $pub->pub,
        '%name%' => $pub->values()['name'],
      ),
    ),
    Language::string(71),
    $url_page);
  return;
}

echo '<div class="pub">';
  // List accessable pubs
  if( count($accessable_pubs) > 1 ) {
    // Multiple access
    echo '<div class="header" onclick="this.children[1].classList.toggle(\'visible\')">';
      echo '<span class="current multiple">' . $pub->values()["name"] . '</span>';
      echo '<div class="pubs">';
        // List all accessable pubs
        foreach( $accessable_pubs as $pub_id ) {
          if( $pub->pub != $pub_id) {
            // Start new pub for name
            $name_pub = new Pub();
            $name_pub->pub = $pub_id;

            echo '<a href="' . $url_page . '&pub=' . $pub_id . '">' . $name_pub->values()["name"] . '</a>';
          }
        }
      echo '</div>';
    echo '</div>';
  } else {
    echo '<div class="header">';
      echo '<span class="current">' . $pub->values()["name"] . '</span>';
    echo '</div>';
  }

  switch(key($action)) {
    case "add":
      // Start product
      $product = new Product();
      $product->pub = $pub->pub;

      if(! empty( $_POST ) ) {
        // Generate array
        $transaction_values = array();
        foreach($_POST as $productID => $quantity) {
          if( is_int( $productID ) && $quantity != 0 ) {
            array_push( $transaction_values, array(
              "productID" => $productID,
              "quantity" => $quantity ?? 0,
            ));
          }
        }

        // Add tip if required
        if( isset($_POST["tip"]) && $_POST["tip"] != 0 ) {
          array_push( $transaction_values, array(
            "productID" => 0,
            "price" => floatval(str_replace(",", ".", $_POST["tip"])) * 100,
            "quantity" => 1,
          ));
        }

        // If success
        $transaction = new Transaction();
        if($transaction->add( $transaction_values, $pub->pub )) {
          // Update E-Mail
          $transaction->update(
            array(
              "email" => $_POST["email"],
            )
          );

          // Success message and redirect
          Action::success( Language::string(90) );
          echo '<script>window.location.href = "' . $url_page . '&view=' . $transaction->paymentID . '"</script>';
        }else {
          Action::fail( Language::string(91) );
        }
      }

      // Get sections
      $sections = $product->sections();
      array_push($sections, array("section" => null));

      // Submenu totol
      echo '<div class="submenu-total">';
        echo '<div class="calculated">';
          echo '<span class="total">' . Language::string(92) . '</span>';
          echo '<span class="price">0.00</span>';
          echo '<span class="currency">' . ($pub->values()["currency"] ?? DEFAULT_CURRENCY) . '</span>';
        echo '</div>';
        echo '<button class="pay" onclick="validateForm(document.getElementsByTagName(\'form\')[0])">' . Language::string(93) . '</button>';
      echo '</div>';

      echo '<form class="products" action="' . $url_page . '&add" method="post">';

        // Email
        echo '<label class="txt-input" style="margin: 10px 0px;">';
          echo '<input type="email" name="email" required="">';
          echo '<span class="placeholder">' . Language::string(94) . '</span>';
        echo '</label>';

        // List through seections
        foreach($sections as $section) {
          // Get all products
          $products = "";
          foreach( $product->products_by_section( $section["section"] ) as $values ) {
            // Check if visible
            $product->product_id = $values["id"];

            if($product->visibility()) {
              $products .= '<div class="row">';
                $products .= '<span class="product">';
                  $products .= $values["name"];
                  // Check image
                  if(! empty($values["product_fileID"])) {
                    $products .= '<span class="icon">&#9432;</span>';
                    $products .= '<div class="product-img" style="background-image: url(\'' . MediaHub::getUrl( $values["product_fileID"] ) . '\')"></div>';
                  }
                $products .= '</span>';
                $products .= '<span class="price">' . number_format(( isset($values["price"]) ? ($values["price"] / 100) : 0), 2) . ' ' . ($pub->values()["currency"] ?? DEFAULT_CURRENCY) . '</span>';
                $products .= '<div class="shoppingbag_options">';
                  $products .= '<span class="remove" onclick="changeQuantity(this.parentNode.children[1], \'remove\')">-</span>';
                  $products .= '<input type="text" name="' . $values["id"] . '" pattern="[0-9]{1,3}" value="0" onchange="change_total_price( this )"/>';
                  $products .= '<span class="add" onclick="changeQuantity(this.parentNode.children[1], \'add\')">+</span>';
                $products .= '</div>';
              $products .= '</div>';
            }
          }

          // Check if any products are listet in this section
          if( strlen($products) > 0) {
            // Show section
            echo'<div class="section-container">';
              echo '<div class="header row">';
                echo '<span class="product">' . ($section["section"] ?? Language::string(95)) . '</span>';
                echo '<span class="accordion"><span onclick="toggle_section(this)">-</span></span>';
              echo '</div>';

              echo '<div class="productlist" style="max-height: 100%;">';
                echo $products; // Show products
              echo '</div>';

            echo '</div>';
          }
        }

        // Check if open amount is allowed
        if( $pub->values()["tip"] === 1) {
          // Show section for tip amount
          echo'<div class="section-container">';
            echo '<div class="header row tip">';
              echo '<span class="product">' . Language::string(96) . '</span>';
              echo '<div class="placeholder-js">';
                echo '<span class="input">';
                  echo'<input type="text" pattern="[0-9\.]{1,3}" name="tip" placeholder="0.00" onkeyup="change_total_price( this )" />';
                  echo ($pub->values()["currency"] ?? DEFAULT_CURRENCY);
                echo  '</span>';
              echo '</div>';
            echo '</div>';
          echo '</div>';
        }

      echo '</form>';
    break;
    case "view":
      // Set ID
      $transaction->paymentID = $_GET["view"];

      // Check payment
      $transaction->paymentCheck();

      // Right menu
      $rightmenu = new HTML('right-menu');

      // PickUp
      if( $write_access ) {
        if( $transaction->globalValues()["pick_up"] == 0 ) {
          $rightmenu->addElement(
            array(
              'context' => '<img src="' . $url . 'medias/icons/pickUp.svg" alt="' . Language::string(0) . '" title="' . Language::string(1) . '"/>',
              'additional_item' => 'onclick="togglePickUp(' . $transaction->paymentID . ', this)"',
            ),
          );
        }else {
          $rightmenu->addElement(
            array(
              'context' => '<img src="' . $url . 'medias/icons/pickedUp.svg" alt="' . Language::string(0) . '" title="' . Language::string(1) . '"/>',
              'additional_item' => 'onclick="togglePickUp(' . $transaction->paymentID . ', this)"',
            ),
          );
        }

        // Payment
        if( $transaction->globalValues()["payment_state"] == 2) {
          $rightmenu->addElement(
            array(
              'context' => '<img src="' . $url . 'medias/icons/payment_confirm.svg" alt="' . Language::string(2) . '" title="' . Language::string(3) . '"/>',
              'additional_item' => 'onclick="confirmPayment(' . $transaction->paymentID . ', this)"',
            ),
          );
        }

        // Refund
        if( isset($transaction->globalValues()["payrexx_transaction"]) &&! empty($transaction->globalValues()["payrexx_transaction"])) {
          $rightmenu->addElement(
            array(
              'context' => '<img src="' . $url . 'medias/icons/payment-refund.svg" alt="' . Language::string(4) . '" title="' . Language::string(5) . '"/>',
              'dropdown' => array(
                array(
                  'context' => '<div class="container">
                                  <input type="text" min="0" max="' . (($transaction->totalPrice() - $transaction->globalValues()["refund"]) / 100) . '"/>' .
                                  '<span class="currency">' . $transaction->globalValues()["currency"] . '</span>' .
                                  '<button onclick="refundPayment(' . $transaction->paymentID . ', this.parentNode.children[0].value)">' . Language::string(6) . '</button>' . '</div>',
                  'classes' => 'no-hover refund-payment',
                ),
              ),
            ),
          );
        }

        // Remove
        if(  $transaction->globalValues()["payment_state"] == 1 || array_search( ($transaction->getGateway()->getInvoices()[0]["transactions"][0]["pspId"] ?? null), array(27, 15) ) != false ) {
          $rightmenu->addElement(
            array(
              'context' => '<img src="' . $url . 'medias/icons/trash.svg" alt="' . Language::string(7) . '" title="' . Language::string(8) . '"/>',
              'additional_item' => 'href="' . $url_page . '&remove=' . $transaction->paymentID . '"',
            ),
          );
        }
      }

      // Display top return button
      $topNav = new HTML('top-nav', array('classes' => 'border-none'));
      $topNav->addElement(
        array(
          'context' => '<img src="' . $url . 'medias/icons/history-back.svg">',
          'link' => 'Javascript:history.back()',
          'additional' => 'title="' . Language::string(9) . '"',
        ),
      );

      // Show HTML
      $topNav->prompt();
      $rightmenu->prompt();

      // Start view
      echo '<div class="view">';

        // Get details
        echo '<div class="details">';
          echo '<span class="headline">' . Language::string(10) . ' #' . $transaction->paymentID . '</span>';
          echo '<div class="details-list">';

            // Email
            echo '<div class="detail-item email">';
              echo '<span class="type">' . Language::string(11) . '</span>';
              echo '<span class="value">' . $transaction->globalValues()["email"] . '</span>';
            echo '</div>';

            // Payment ID
            echo '<div class="detail-item paymentID">';
              echo '<span class="type">' . Language::string(12) . '</span>';
              echo '<span class="value">' . $transaction->paymentID . '</span>';
            echo '</div>';

            // Price
            echo '<div class="detail-item amount">';
              echo '<span class="type">' . Language::string(13) . '</span>';
              echo '<span class="value">' . number_format(($transaction->totalPrice() / 100), 2) . ' ' . $transaction->globalValues()["currency"] . '</span>';
            echo '</div>';

            // Price
            echo '<div class="detail-item new_amount">';
              echo '<span class="type">' . Language::string(14) . '</span>';
              echo '<span class="value">' . number_format((($transaction->totalPrice() - $transaction->globalValues()["refund"]) / 100), 2) . ' ' . $transaction->globalValues()["currency"] . '</span>';
            echo '</div>';

            // Refund
            echo '<div class="detail-item refund">';
              echo '<span class="type">' . Language::string(15) . '</span>';
              echo '<span class="value">' . number_format(($transaction->globalValues()["refund"] / 100), 2) . ' ' . $transaction->globalValues()["currency"] . '</span>';
            echo '</div>';

            // Fees
            echo '<div class="detail-item fees">';
              echo '<span class="type">' . Language::string(16) . '</span>';
              echo '<span class="value">' . number_format(($transaction->totalFees() / 100), 2) . ' ' . $transaction->globalValues()["currency"] . '</span>';
            echo '</div>';

            // Payment state
            echo '<div class="detail-item state">';
              echo '<span class="type">' . Language::string(17) . '</span>';
              echo '<span class="value">';
                if( $transaction->globalValues()["payment_state"]  == 2 && $transaction->globalValues()["pick_up"] == 1 ) { // Payment expected and picked up
                  echo Language::string(18);
                }elseif ( $transaction->globalValues()["payment_state"]  == 2 ) { // Payment expected
                  echo Language::string(19);
                }elseif( $transaction->globalValues()["pick_up"] == 0 ) { // not picked up
                  echo Language::string(20);
                }else {
                  echo Language::string(21);
                }
              echo '</span>';
            echo '</div>';

            // Payment option
            echo '<div class="detail-item paymentOption">';
              echo '<span class="type">' . Language::string(22) . '</span>';
              echo '<span class="value">';
                if(  $transaction->globalValues()["payment_state"] != 1 && array_search( ($transaction->getGateway()->getInvoices()[0]["transactions"][0]["pspId"] ?? null), array(27, 15) ) === false ) {
                  echo Language::string(23);
                }else {
                  echo Language::string(24);
                }
              echo '</span>';
            echo '</div>';

            // Payment time
            echo '<div class="detail-item paymentTime">';
              echo '<span class="type">' . Language::string(25) . '</span>';
              echo '<span class="value">' . date("d.m.Y H:i:s", strtotime($transaction->globalValues()["payment_time"])) . '</span>';
            echo '</div>';

          echo '</div>';
        echo '</div>';

        // Products
        echo '<div class="products">';
          // Headline
          echo '<div class="headline">' . Language::string(26) . '</div>';

          foreach( $transaction->values() as $product ) {
            // Get product
            $productName = new Product();
            $productName->product_id = $product["product_id"];

            echo '<div class="row">';
              echo '<span class="quantity">' . ($product["quantity"] ?? 1) . 'x</span>';
              echo '<span class="name">' . ($product["product_id"] == 0 ? Language::string(27) :
                ($productName->values()["name"] ?? Language::string(28))) . '</span>';
              echo '<span class="price">' . number_format(($product["price"] / 100), 2) . ' ' . $transaction->globalValues()["currency"] . '</span>';
            echo '</div>';
          }

          // Total
          echo '<div class="total">';
            echo '<span class="text">' . Language::string(29) . '</span>';
            echo '<span class="value">' . number_format(($transaction->totalPrice() / 100), 2) . ' ' . $transaction->globalValues()["currency"] . '</span>';
          echo '</div>';
        echo '</div>';

      echo '</div>';
    break;
    case "remove":
      // Get name of pub
      $transaction->paymentID = $_GET["remove"] ?? null;

      // Generate message
      $info = Language::string( 60,
        array(
          '%email%' => $transaction->globalvalues()["email"],
          '%id%' => $_GET["remove"],
        ),
      );

      // Display message
      Action::confirm($info, $_GET["remove"], "&pub=" . $_GET["pub"]);
    break;
    default:
      if(isset($_POST["confirm"])) {
        if( $write_access ) {
          // Get values
          $transaction->paymentID = $_POST["confirm"];
          $email = $transaction->globalValues()["email"];

          // Remove
          if( $transaction->remove() ) {
            Action::success( Language::string( 54,
              array(
                '%email%' => $email,
                '%id%' => $_POST["confirm"],
              ), )
            );
          }else {
            Action::fail( Language::string( 55,
              array(
                '%email%' => $email,
                '%id%' => $_POST["confirm"],
              ), )
            );
          }
        }else {
          Action::fail( Language::string(56) );
        }
      }

      // Start searchbar
      $searchbar = new HTML('searchbar', array(
        'action' => $url,
        'method' => 'get',
        'placeholder' => Language::string(37),
        's' => ($_GET['s'] ?? ''),
      ));

      $searchbar->addElement( '<input type="hidden" name="id" value="' . $mainPage . '" />' );
      $searchbar->addElement( '<input type="hidden" name="sub" value="' . $page . '" />' );
      $searchbar->addElement( '<input type="hidden" name="pub" value="' . ($_GET["pub"] ?? null) . '" />' );

      // Define colors
      $availability = array(
        0 => array(
          "bcolor" => "var(--transaction-payment-and-pickUp)",
          "title" => Language::string(38),
        ),
        1 => array(
          "bcolor" => "var(--transaction-payment-expected)",
          "title" => Language::string(39),
        ),
        2 => array(
          "bcolor" => "var(--transaction-no-pickUp)",
          "title" => Language::string(40),
        ),
      );

      //Start Legend
      $legend = new HTML('legend');

      foreach( $availability as $values ) {
        $legend->addElement( array(
          'bcolor' => $values['bcolor'],
          'title' => $values['title']
        ) );
      }


      // Start table
      $table = new HTML('table');

      // Headline
      $table->addElement(
        array(
          'headline' => array(
            'items' => array(
              array(
                'context' => Language::string(41),
                'additional' => 'style="width: 1%; white-space:nowrap;"',
              ),
              array(
                'context' => Language::string(42),
              ),
              array(
                'context' => Language::string(43),
              ),
              array(
                'context' => Language::string(44),
              ),
              array(
                'context' => Language::string(45),
              ),
            ),
          ),
        ),
      );

      // Set offset and steps
      $steps = 20;
      $offset = (isset($_GET["row-start"]) ? ($_GET["row-start"] * $steps) : 0);

      // List general products
      foreach( $transaction->all( $offset, $steps, ($_GET["s"] ?? null)) as $values ) {
        // Set paymentID
        $transaction->paymentID = $values["paymentID"];

        // Check payment
        $transaction->paymentCheck();

        // Generate class
        if( $transaction->globalValues()["payment_state"]  == 2 && $transaction->globalValues()["pick_up"] == 1 ) { // Payment expected and picked up
          $class = "transaction payment-and-pickUp";
          $title = Language::string(46);
        }elseif ( $transaction->globalValues()["payment_state"]  == 2 ) { // Payment expected
          $class = "transaction payment-expected";
          $title = Language::string(47);
        }elseif( $transaction->globalValues()["pick_up"] == 0 ) { // not picked up
          $class = "transaction no-pickUp";
          $title = Language::string(48);
        }else {
          $class = "transaction";
          $title = Language::string(49);
        }

        // Create actions
        $action = '<a href="' . $url_page . '&pub=' . urlencode( $pub->pub ) . '&view=' . urlencode( $transaction->paymentID ) . '"
                    title="' . Language::string(50) . '"><img src="' . $url . '/medias/icons/view-eye.svg"/></a>';
        if( $transaction->globalValues()["payment_state"] == 1 || // manually payment
            $transaction->globalValues()["payment_state"] == 2 || // payment expected
            array_search( ($transaction->getGateway()->getInvoices()[0]["transactions"][0]["pspId"] ?? null), array(27, 15) ) != false ) {
          $action .=  '<a href="' . $url_page . '&pub=' . urlencode( $pub->pub ) . '&remove=' . urlencode( $transaction->paymentID ) . '"
                        title="' . Language::string(51) . '"><img src="' . $url . '/medias/icons/trash.svg"/></a>';
        }

        $table->addElement(
          array(
            'row' => array(
              'additional' => 'class="' . $class . '" title="' . $title . '" id="' . $transaction->paymentID . '"',
              'items' => array(
                array(
                  'context' => '#' . $transaction->paymentID,
                  'additional' => 'style="width: 1%; white-space:nowrap;"',
                ),
                array(
                  'context' => $transaction->globalValues()["email"],
                ),
                array(
                  'context' => number_format(($transaction->totalPrice() / 100), 2) . ' ' .
                                ($transaction->globalValues()["currency"] ?? DEFAULT_CURRENCY),
                ),
                array(
                  'context' => date("d.m.Y H:i:s", strtotime($transaction->globalValues()["payment_time"])),
                ),
                array(
                  'context' => $action,
                ),
              ),
            ),
          ),
        );
      }

      // Footer
      $last = '<a href="' .
                $url_page .
                (isset($_GET["pub"]) ? "&pub=" . urlencode($_GET["pub"]) : "") .
                ( isset($_GET["s"]) ? "&s=" . urlencode($_GET["s"]) : "" ) .
                '&row-start=' . round($offset/$steps - 1, PHP_ROUND_HALF_UP) . '"
                style="float: left;">' . Language::string(52) . '</a>';
      $next = '<a href="' .
                $url_page .
                (isset($_GET["pub"]) ? "&pub=" . urlencode($_GET["pub"]) : "") .
                ( isset($_GET["s"]) ? "&s=" . urlencode($_GET["s"]) : "" ) .
                '&row-start=' . round($offset/$steps + 1, PHP_ROUND_HALF_UP) . '"
                style="float: right;">' . Language::string(53) . '</a>';

      if( (count($transaction->all( ($offset + $steps), 1, ($_GET["s"] ?? null))) > 0) && (($offset/$steps) > 0) ) { // More and less pages accessable
        $table->addElement(
          array(
            'footer' => array(
              'context' => $last . $next,
            ),
          ),
        );
      }elseif ( ($offset/$steps) > 0 ) { // Less pages accessables
        $table->addElement(
          array(
            'footer' => array(
              'context' => $last,
            ),
          ),
        );
      }elseif (count($transaction->all( ($offset + $steps), 1, ($_GET["s"] ?? null))) > 0) { // More pages accessable
        $table->addElement(
          array(
            'footer' => array(
              'context' => $next,
            ),
          ),
        );
      }

      // enerate box
      $global = true;

      echo '<div class="earning-box">';
        // Toggle
        echo '<div class="toggle">';
          echo '<div class="container">';
            echo '<span class="text current" data-current="0" onclick="toggleEarningBox(0, ' . $pub->pub . ')" title="' . Language::string(30) . '">' . Language::string(31) . '</span>';
            echo '<span class="text" data-current="1" onclick="toggleEarningBox(1, ' . $pub->pub . ')" title="' . Language::string(32) . '">' . Language::string(33) . '</span>';
          echo '</div>';
        echo '</div>';

        // Main text
        echo '<div class="earned">';
          echo '<span class="text" title="' . Language::string(34) . '">' . number_format( ($pub->earned( $global ) - $pub->fees( $global ) - $pub->refunded( $global )) / 100 , 2) . " " . ($pub->values()["currency"] ?? DEFAULT_CURRENCY) .'</span>';
        echo '</div>';

        // Subinfo
        echo '<div class="info">';
          echo '<div class="fees">';
            echo '<span class="name">' . Language::string(35) . '</span>';
            echo '<span class="value">' . number_format( ($pub->fees( $global ) / 100), 2) . ' ' . ($pub->values()["currency"] ?? DEFAULT_CURRENCY) . '</span>';
          echo '</div>';
          echo '<span class="divider">|</span>';
          echo '<div class="refund">';
            echo '<span class="name">' . Language::string(36) . '</span>';
            echo '<span class="value">' . number_format( ($pub->refunded( $global ) / 100), 2) . ' ' . ($pub->values()["currency"] ?? DEFAULT_CURRENCY) . '</span>';
          echo '</div>';
        echo '</div>';
      echo '</div>';

      // Show HTML
      $searchbar->prompt();
      $legend->prompt();
      $table->prompt();

      //Add button
      if( $write_access ) {
        echo '<a class="add" href="' . $url_page . '&add">
          <span class="horizontal"></span>
          <span class="vertical"></span>
        </a>';
      }


      echo '<script>';
        echo 'setInterval( function() {';
          echo 'var current = document.getElementsByClassName("earning-box")[0].getElementsByClassName("toggle")[0].getElementsByClassName("current")[0];';
          echo 'if( current.getAttribute("data-current") == 0 ) {';
            echo 'earningBoxValues( ' . $pub->pub . ', true);';
          echo '}else if ( current.getAttribute("data-current") == 1 ) {';
            echo 'earningBoxValues( ' . $pub->pub . ', false);';
          echo  '}';
          echo 'loadTransactions(' . $steps . ', ' . $offset .', ' . ($_GET["s"] ?? 'null') . ', ' . $transaction->pub . ');';
        echo '}, 2000);';
      echo '</script>';
    break;
  }
