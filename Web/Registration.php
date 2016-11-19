<?php
namespace Markaos\BakAPI\Web {
  class Registrator extends BasePage {
    public function onRequest() {
      $this->setTitle("Přihlášení");
      if (
          !isset($_POST["name"]) || !isset($_POST["password"]) ||
          !isset($_POST["server"]) || !isset($_POST["keepAccount"])
      ) {
        $this->setContents(
        ContentBuilder::makeBlock()
          ->addClass("container")
          ->addContentNode(
            ContentBuilder::makeBlock()
              ->addClass("valign-wrapper")
              ->addClass("row")
              ->setAttribute("style", "height: 100%")
              ->addContentNode(
                ContentBuilder::makeForm()
                  ->setId("loginForm")
                  ->setClass("form")
                  ->addClass("valign")
                  ->addClass("col col s12 m8 offset-m2 l4 offset-l4")
                  ->setAttribute("action", "?frontend=cz.markaos.bakapi.web")
                  ->setAttribute("method", "POST")
                  ->addContentNode(
                    ContentBuilder::makeInput()
                      ->setId("name")
                      ->setAttribute("name", "name")
                      ->setAttribute("type", "text")
                      ->setAttribute("label", "Jméno")
                      ->build()
                  )
                  ->addContentNode(
                    ContentBuilder::makeInput()
                      ->setId("password")
                      ->setAttribute("name", "password")
                      ->setAttribute("type", "password")
                      ->setAttribute("label", "Heslo")
                      ->build()
                  )
                  ->addContentNode(
                    ContentBuilder::makeInput()
                      ->setId("server")
                      ->setAttribute("name", "server")
                      ->setAttribute("type", "url")
                      ->setAttribute("label", "Server")
                      ->build()
                  )
                  ->addContentNode(
                    ContentBuilder::makeInput()
                      ->setId("keep")
                      ->setClass("hidden")
                      ->setAttribute("name", "keepAccount")
                      ->setAttribute("type", "hidden")
                      ->setAttribute("value", "false")
                      ->build()
                  )
                  ->addContentNode(
                    ContentBuilder::makeBlock()
                      ->addClass("row")
                      ->addContentNode(
                        ContentBuilder::makeButton()
                          ->setId("submitButton")
                          ->addClass("btn")
                          ->addClass("waves-effect")
                          ->addClass("waves-light")
                          ->addClass("col s12 m8 offset-m2 l6 offset-l3")
                          ->addClass("green")
                          ->setAttribute("type", "submit")
                          ->setContents("<span class=\"center-align\">Přihlásit</span>")
                          ->build()
                      )
                      ->build()
                  )
                  ->build()
              )
              ->build()
          )
          ->build()
        );
        $this->finish();
      } else {
        $db = \Markaos\BakAPI\Util::getDatabase();
        $db->createTable("WebCache", [
          "UID"       => "string:256",
          "server"    => "string:128",
          "username"  => "string:128",
          "token"     => "string:129"
        ]);
        $columns = ["UID"];
        $conditions = [
          [
            "column" => "username",
            "condition" => "equals",
            "value" => $_POST["name"]
          ],
          [
            "column" => "server",
            "condition" => "equals",
            "value" => $_POST["server"]
          ]
        ];
        $r = $db->query("WebCache", $columns, $conditions, false);
        $res = array();
        $password = hash("sha512", $_POST["password"]);
        var_dump($r);
        if(count($r) == 1 && $r[0]["token"] == $password) {
          $res = ["status" => true, "result" => $r[0]["UID"]];
        } else {
          $res = \Markaos\BakAPI\BakAPI::register(
            $_POST["server"], $_POST["name"], $_POST["password"]
          );
          if($res["status"] == true) {
            $columns = ["UID", "server", "username", "token"];
            $values = [[$res["result"], $_POST["server"], $_POST["name"], $password]];
            $db->insert("WebCache", $columns, $values);
          }
        }
        if($res["status"] === false) {
          $error = $res["result"] == BAKAPI_ERROR_SERVER_UNSUPPORTED ?
            "Nepodařilo se připojit k serveru" : "Špatné jméno nebo heslo";
          $this->setContents(
          ContentBuilder::makeBlock()
            ->addClass("container")
            ->addContentNode(
              ContentBuilder::makeBlock()
                ->addClass("valign-wrapper")
                ->addClass("row")
                ->setAttribute("style", "height: 100%")
                ->addContentNode(
                  ContentBuilder::makeForm()
                    ->setId("loginForm")
                    ->setClass("form")
                    ->addClass("valign")
                    ->addClass("col col s12 m8 offset-m2 l4 offset-l4")
                    ->setAttribute("action", "?frontend=cz.markaos.bakapi.web")
                    ->setAttribute("method", "POST")
                    ->addContentNode(
                      ContentBuilder::makeText()
                        ->addClass("flow-text")
                        ->addClass("red-text")
                        ->setAttribute("style", "font-weight: 500")
                        ->setContents($error)
                        ->build()
                    )
                    ->addContentNode(
                      ContentBuilder::makeInput()
                        ->setId("name")
                        ->setAttribute("name", "name")
                        ->setAttribute("type", "text")
                        ->setAttribute("label", "Jméno")
                        ->build()
                    )
                    ->addContentNode(
                      ContentBuilder::makeInput()
                        ->setId("password")
                        ->setAttribute("name", "password")
                        ->setAttribute("type", "password")
                        ->setAttribute("label", "Heslo")
                        ->build()
                    )
                    ->addContentNode(
                      ContentBuilder::makeInput()
                        ->setId("server")
                        ->setAttribute("name", "server")
                        ->setAttribute("type", "url")
                        ->setAttribute("label", "Server")
                        ->build()
                    )
                    ->addContentNode(
                      ContentBuilder::makeInput()
                        ->setId("keep")
                        ->setClass("hidden")
                        ->setAttribute("name", "keepAccount")
                        ->setAttribute("type", "hidden")
                        ->setAttribute("value", "false")
                        ->build()
                    )
                    ->addContentNode(
                      ContentBuilder::makeBlock()
                        ->addClass("row")
                        ->addContentNode(
                          ContentBuilder::makeButton()
                            ->setId("submitButton")
                            ->addClass("btn")
                            ->addClass("waves-effect")
                            ->addClass("waves-light")
                            ->addClass("col s12 m8 offset-m2 l6 offset-l3")
                            ->addClass("green")
                            ->setAttribute("type", "submit")
                            ->setContents("<span class=\"center-align\">Přihlásit</span>")
                            ->build()
                        )
                        ->build()
                    )
                    ->build()
                )
                ->build()
            )
            ->build()
          );
          $this->finish();
        } else {
          $_SESSION["name"] = $_POST["name"];
          $_SESSION["server"] = $_POST["server"];
          $_SESSION["UID"] = $res["result"];
          $this->getContext()->handleRequest();
        }
      }
    }
  }
}
?>
