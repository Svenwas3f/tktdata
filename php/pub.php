<?php
/**
 ************* General *************
 * @Author: Sven Waser
 * @System: TKTData
 * @Version: 1.0
 * @Published: Mai 2021
 * @Purpose: File to manage pub ations
 *
 ************* Class Variables *************
 * If a function requires such a variable, you will find a hint in the comments of the function
 * $pub: pub Id for pub
 * $product_id: Id of requested product
 *
 **************** All functions ****************
 * For further description please go to requested function
 * Some functions uses class variable. Those are written behind the function name in square brackets [].
 * Variables witch have to be passd through the function are written after the function name inround brackets ().
 * All functions can be used as Static
 *
 * pub->all ( $offset [int], $steps [int], $search_value [info_string] )
 *
 * pub->add ( $table [const], $values [array] ) [$pub]
 *
 * pub->update ( $values [array] ) [$pub]
 *
 * pub->remove () [$pub]
 *
 * pub->remove_access ( $user [int] ) [$pub]
 *
 * pub->access ( $user [int or null], $offset [int], $steps [int] ) [$pub]
 *
 * pub->accessable ( $user [string] )
 *
 * pub->values () [$pub]
 *
 *
 **************** availability explanation ****************
 *
 * AVAILABILITY
 * 0: available
 * 1: little available
 * 2: sold
 *
 */
class Pub {
  //Variables
  public $pub;
  public $product_id;

  //constants
  const DEFAULT_TABLE = PUB;
  const PRODUCTS_TABLE = PUB_PRODUCTS;
  const ACCESS_TALBE = PUB_ACCESS;

  /**
   * Returns array of all pubs
   *
   * $limit: How many rows
   * $offset: Start row
   * $search_value: Search string
   */
  public function all( $offset = 0, $steps = 20, $search_value = null ) {
    //Get database connection
    $conn = Access::connect();

    if( is_null($search_value) || empty($search_value) ) {
      // Select all
      $pub = $conn->prepare("SELECT * FROM " . PUB . " ORDER BY name ASC LIMIT " . $steps . " OFFSET " . $offset);
      $pub->execute();
    }else {
      // Select all
      $pub = $conn->prepare("SELECT * FROM " . PUB . " WHERE pub_id=:pub_id OR name LIKE :name  ORDER BY name ASC LIMIT " . $steps . " OFFSET " . $offset);
      $pub->execute(array(
        ":pub_id" => $search_value,
        ":name" => "%" . $search_value . "%"
      ));
    }

    return $pub->fetchAll( PDO::FETCH_ASSOC );
  }

  /**
   * Adds a new row in requested table
   *
   * $table: Uses Table name use class constants (Supports DEFAULT_TABLE and PRODUCTS_TABLE)
   * $values: Array with new values
   *          DFAULT TABLE:   array(
   *                            name,
   *                            payment_payrexx_instance,
   *                            payment_payrexx_secret,
   *                            payment_fee_absolute, (100 = 1)
   *                            payment_fee_percent (1000 = 100%)
   *                          )
   *          PRODUCTS_TABLE: array(
   *                            pub_id,
   *                            name,
   *                            section,
   *                            price,
   *                            product_fileID,
   *                          )
   *          ACCESS_TABLE:   array(
   *                            pub_id,
   *                            user_id
   *                          )
   */
  public function add( $table = SELF::DEFAULT_TABLE, $values ) {
    // Get global
    global $current_user;

    //Get database connection
    $conn = Access::connect();

    // Check values
    $valid_keys = array("pub_id", "name", "description", "logo_fileID", "background_fileID", "payment_payrexx_instance", "currency", "payment_payrexx_secret", "payment_fee_absolute", "payment_fee_percent", "tip", "id", "user_id", "w", "r", "section", "price", "product_fileID");
    $checked_values = array_intersect_key($values, array_flip($valid_keys));

    //Generate query
    $add_query = "INSERT INTO " . $table . " ";
    $add_query .= "(" . implode(", ", array_keys($checked_values)) . ") ";
    $add_query .= "VALUES ('" . implode("', '", $checked_values) . "')";

    // Restore message
    // $restore_message = array(
    //   "pub" => "Added new pub (" . ($checked_values["name"] ?? '') . ")",
    //   "product" => "Added new" . (is_null($checked_values["pub_id"] ?? null) ? " global" : " ") .  " product (" . ($checked_values["name"] ?? "unknown") . ") " . (is_null($checked_values["pub_id"] ?? null) ? "" : ("for pub #" . $checked_values["pub_id"])),
    //   "access" => "Added access to pub #" . ($checked_values["pub_id"] ?? "unknown") . " for User (" . $current_user . ") " . User::name( $current_user ),
    // );

    $restore_message = array(
      "pub" => array(
        "id" => 110,
        "replacements" => array(
          "%name%" => ($checked_values["name"] ?? ''),
        ),
      ),

      "product" => array(
        "id" => (is_null($checked_values["pub_id"] ?? null) ? 111 : 112),
        "replacements" => array(
          "%name%" => $checked_values["name"] ?? "unknown",
          "%pub%" => $checked_values["pub_id"] ?? "unknown",
        ),
      ),

      "access" => array(
        "id" => 113,
        "replacements" => array(
          "%pub%" => ($checked_values["pub_id"] ?? "unknown"),
          "%user%" => $current_user,
          "%name%" => User::name( $current_user ),
        ),
      ),
    );

    //Create modification
    $change = array(
      "user" => $current_user,
      "message" => json_encode(($table == SELF::DEFAULT_TABLE ? $restore_message["pub"] : ($table == SELF::PRODUCTS_TABLE ? $restore_message["product"] : $restore_message["access"]))),
      "table" => ($table == SELF::DEFAULT_TABLE ? "pub" : ($table == SELF::PRODUCTS_TABLE ? "PUB_PRODUCTS" : "PUB_ACCESS")),
      "function" => "INSERT INTO",
      "primary_key" => array("key" => "pub_id", "value" => ""),
      "old" => "",
      "new" => $checked_values
    );

    User::modifie( $change );

    // execute query
    $add = $conn->prepare($add_query);
    $result = $add->execute();

    if( $result === true ) {
      if($table == SELF::DEFAULT_TABLE) {
        $this->pub = $conn->lastInsertId();
      } elseif($table == SELF::PRODUCTS_TABLE) {
        $this->product_id = $conn->lastInsertId();
      }
      return true;
    }else {
      return false;
    }
  }

  /**
   * Updates a pub
   * requires: $pub
   *
   * $values: Array with new values
   *          array(
   *            name,
   *            logo_fileID,
   *            background_fileID,
   *            payment_payrexx_instance,
   *            payment_payrexx_secret,
   *            payment_fee_absolute, (100 = 1)
   *            payment_fee_percent (10000 = 100%)
   *         )
   */
  public function update( $values) {
    // Get global
    global $current_user;

    //Get database connection
    $conn = Access::connect();

    // Check values
    $valid_keys = array("name", "logo_fileID", "description", "background_fileID", "currency", "payment_payrexx_instance", "payment_payrexx_secret", "payment_fee_absolute", "payment_fee_percent", "tip");
    $checked_values = array_intersect_key($values, array_flip($valid_keys));

    // Generate values and keys
    $update_query = "UPDATE " . PUB . " SET ";
    foreach( $checked_values as $key => $value ) {
      if( strlen($value) > 0) { // empty does handle 0 as no value
        $update_query .= $key . " = '" . $value . "', ";
      }
    }
    $update_query = substr( $update_query, 0, -2 ) . " WHERE pub_id=:pub_id";

    //Modifie
    $change = array(
      "user" => $current_user,
      // "message" => "Updated pub #" . $this->pub . " (" . $this->values()["name"] . ")",
      "message" => json_encode(array(
        "id" => 114,
        "replacements" => array(
          "%pub%" => $this->pub,
          "%name%" => $this->values()["name"],
        ),
      ),),
      "table" => "PUB",
      "function" => "UPDATE",
      "primary_key" => array("key" => "pub_id", "value" => $this->pub),
      "old" => array_intersect_key($this->values(), array_flip($valid_keys)),
      "new" => $checked_values
    );

    User::modifie($change);

    // Update query
    $update = $conn->prepare( $update_query );
    return $update->execute(array(
      ":pub_id" => $this->pub,
    ));
  }

  /**
   * Removes a pub
   * requires: $pub
   */
  public function remove() {
    // Get global
    global $current_user;

    //Get database connection
    $conn = Access::connect();

    //Modifie
    $change = array(
      "user" => $current_user,
      // "message" => "Removed pub #" . $this->pub . " (" . $this->values()["name"] . ")",
      "message" => json_encode(array(
        "id" => 115,
        "replacements" => array(
          "%pub%" => $this->pub,
          "%name%" => $this->values()["name"],
        ),
      ),),
      "table" => "PUB",
      "function" => "UPDATE",
      "primary_key" => array("key" => "pub_id", "value" => $this->pub),
      "old" => array_intersect_key($this->values(), array_flip(array("name", "logo_fileID", "background_fileID", "payment_payrexx_instance", "payment_payrexx_secret"))),
      "new" => array("")
    );

    User::modifie($change);

    // Remove
    $remove = $conn->prepare("DELETE FROM " . PUB . " WHERE pub_id=:pub_id");
    return $remove->execute(array(
      ":pub_id" => $this->pub,
    ));
  }

  /**
   * Removes an access
   * requires: $pub
   *
   * $user: User id (stored in database)
   */
  public function remove_access( $user ) {
    // Get global
    global $current_user;

    //Get database connection
    $conn = Access::connect();

    // Get ID of access
    $access_id = $conn->prepare("SELECT * FROM " . PUB_ACCESS . " WHERE pub_id=:pub_id AND user_id=:user_id");
    $access_id->execute(array(
      ":pub_id" => $this->pub,
      ":user_id" => $user,
    ));
    $id = $access_id->fetch( PDO::FETCH_ASSOC )["id"] ?? false;

    //Modifie
    $change = array(
      "user" => $current_user,
      // "message" => "Removed access for User #" . $user . " (" . User::name( $user ) . ") on pub #" . $this->pub . " (" . $this->values()["name"] . ")",
      "message" => json_encode(array(
        "id" => 116,
        "replacements" => array(
          "%user%" => $user,
          "%username%" => User::name( $user ),
          "%pub%" => $this->pub,
          "%pubname%" => $this->values()["name"],
        ),
      ),),
      "table" => "PUB_ACCESS",
      "function" => "UPDATE",
      "primary_key" => array("key1" => "pub_id", "value1" => $this->pub, "key2" => "user_id", "value2" => $user),
      "old" => ((empty(array_column($this->access(), "id")) ? "" : $this->access()[array_search( $id, array_column($this->access(), "id"))])),
      "new" => array("")
    );

    User::modifie($change);

    // Remove
    $remove = $conn->prepare("DELETE FROM " . PUB_ACCESS . " WHERE pub_id=:pub_id AND user_id=:user_id");
    return $remove->execute(array(
      ":pub_id" => $this->pub,
      ":user_id" => $user,
    ));
  }

  /**
   * Returns true or false or if user is equal to null it returns list of users who have access to the pub
   * requires: $pub
   *
   * $user = User Id or null (Lists all user with access to this pub)
   * $offset: at what row you want to start
   * $steps: How many rows
   */
  public function access( $user = null, $offset = 0, $steps = 20 ) {
    //Get database connection
    $conn = Access::connect();

    if( is_null($user) ) {
      // Get all users that have access to this pub
      $users = $conn->prepare("SELECT * FROM " . PUB_ACCESS . " WHERE user_id=:user_id LIMIT " . $steps . " OFFSET " . $offset);
      $users->execute(array(
        ":user_id" => $user,
      ));

      return $users->fetchAll( PDO::FETCH_ASSOC );
    }else {
      // Check if user has access to this pub
      $check = $conn->prepare("SELECT * FROM " . PUB_ACCESS . " WHERE user_id=:user_id AND pub_id=:pub_id");
      $check->execute(array(
        ":user_id" => $user,
        ":pub_id" => $this->pub,
      ));
      $rights = $check->fetch( PDO::FETCH_ASSOC );

      // Check if user exists
      return array_intersect_key(($rights === false ? array() : $rights), array_flip(array("w", "r")));
    }
  }

  /**
  * Function to get all accessable pubs
  *
  * $user: User ID for whom you want to know the accessable pubs
  */
  public function accessable( $user ) {
    //Get database connection
    $conn = Access::connect();

    // SQL Request
    $accessable = $conn->prepare("SELECT pub_id FROM " . PUB_ACCESS . " WHERE user_id=:user_id AND( w=1 OR r=1)");
    $accessable->execute(array(
      ":user_id" => $user,
    ));

    // Modifie result
    $result = array_map(function($v) { return $v[0]; }, $accessable->fetchAll( PDO::FETCH_NUM ));

    return $result;
  }

  /**
   * Returns all values from access, products and all general values of this pub
   * requires: $pub
   */
  public function values() {
    //Get database connection
    $conn = Access::connect();

    // Get all values from pub
    $pub = $conn->prepare("SELECT * FROM " . PUB . " WHERE pub_id=:pub_id");
    $pub->execute(array(
      ":pub_id" => $this->pub,
    ));
    return $pub->fetch( PDO::FETCH_ASSOC );
  }
}
 ?>
