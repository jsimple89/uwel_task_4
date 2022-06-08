<?php

class App
{
    const USER = 'admin';

    const PASSWORD = 'admin';

    protected $_find_text = '';

    protected $_replace_text = '';

    protected $_action_do;

    protected $_is_post = false;

    protected $_pdo;

    protected $_messages = [];

    public function run()
    {
        $this->init();
        $this->render();
    }

    protected function init()
    {
        if (!empty($_POST)) {
            $this->_is_post = true;
            $this->_find_text = $_POST['find_text'] ? htmlentities($_POST['find_text']) : '';
            $this->_replace_text = $_POST['replace_text'] ? htmlentities($_POST['replace_text']) : '';
            if (!empty($_POST['replace'])) {
                $this->_action_do = 'replace';
            }

            if (!empty($_POST['find'])) {
                $this->_action_do = 'find';
            }

            try {
                $this->_pdo = new PDO("mysql:host=localhost", self::USER, self::PASSWORD);
            } catch (PDOException $e) {
                $this->_messages[] = "Error!: " . $e->getMessage();
            }
        }
    }

    protected function renderForm(){
        $html = "<br><form method='post'>";
        $html .= "<label> Find text:<input type='text' name='find_text' value='{$this->_find_text}'/></label>";
        $html .= "<label>Replace text: <input type='text' name='replace_text' value='{$this->_replace_text}'> </label>";
        $html .= "<input type='submit' name='find' value='find'/>";
        $html .= "<input type='submit' name='replace' value='replace'/>";
        if($this->_is_post && $this->_action_do == 'find')
            $html .= $this->renderResultFind();

        if($this->_is_post && $this->_action_do == 'replace')
            $html .= $this->doReplace();
        $html .= '</form>';
        return $html;
    }

    protected function renderResultFind(){
        $html = '';
        $result = $this->findTables();
        if($result->rowCount()){
            $rows = 0;
            $html .= '<br><hr><br><table border="1">';
            foreach ($result as $table){
                foreach ($this->findText($table['TABLE_SCHEMA'], $table['TABLE_NAME'], $table['COLUMN_NAME'] ) as $row){
                    $findKeys = $this->findKeys($table['TABLE_SCHEMA'], $table['TABLE_NAME']);
                    $html .= "<tr><td>";
                    if($findKeys->rowCount()){
                        $html .= "<input type='checkbox' name='rows[{$rows}][allow]' value='1'>";
                        $html .=  "<input type='hidden' name='rows[{$rows}][table]' value='{$table['TABLE_SCHEMA']}.{$table['TABLE_NAME']}'>";
                        $html .=  "<input type='hidden' name='rows[{$rows}][column]' value='{$table['COLUMN_NAME']}'>";
                        foreach ($findKeys as $key => $keys) {
                            $html .= "<input type='hidden' name='rows[{$rows}][where][{$key}][column]' value='{$keys['Column_name']}'>";
                            $html .= "<input type='hidden' name='rows[{$rows}][where][{$key}][value]' value='{$row[$keys['Column_name']]}'>";
                        }
                    }

                    $html .= "</td>
                            <td>{$table['TABLE_SCHEMA']}.{$table['TABLE_NAME']}.{$table['COLUMN_NAME']}</td>
                            <td>{$row[$table['COLUMN_NAME']]}</td>
                            </tr>";
                    $rows++;
                }

            }
            $html .= '</table><br><hr><br>';
        } else {
            $this->_messages[] = "Not found";
        }
        return $html;
    }

    protected function doReplace(){
        $html = '';
        if(!empty($_POST['rows'])){
            $html .= '<br><hr><br>';
            foreach ($_POST['rows'] as $row){
                if(!empty($row['allow'])){
                    $html .= "Replace: {$this->_find_text} ====> {$this->_replace_text}".json_encode($row).PHP_EOL;
                    $this->replaceText($row);
                }
            }

        } else {
            $this->_messages[] = "Error!: Find and checked replace";
        }
        return $html;
    }

    protected function findTables(){
        return  $this->_pdo->query("SELECT c.* from INFORMATION_SCHEMA.columns c 
            INNER JOIN INFORMATION_SCHEMA.tables t ON t.table_name = c.table_name
            WHERE c.data_type = 'varchar'");
    }

    protected function findText($table_schema, $table_name, $column_name){
        try {
            $result = $this->_pdo->query("SELECT * FROM {$table_schema}.{$table_name} WHERE `{$column_name}` LIKE '%{$this->_find_text}%'");
        } catch (PDOException $e){
            $result =  [];
            $this->_messages[] = "Error!: " . $e->getMessage();
        }
        return $result;
    }
    protected function findKeys($table_schema, $table_name){
        return  $this->_pdo->query("SHOW KEYS FROM {$table_schema}.{$table_name}");
    }

    protected function replaceText($row){
        $where = [];
        foreach ($row['where'] as $item){
            $where[] = implode('=', $item);
        }
        $where = implode(' AND ', $where);
        $this->_pdo->query("UPDATE {$row['table']} SET {$row['column']}= REPLACE({$row['column']}, '{$this->_find_text}', '{$this->_replace_text}') WHERE {$where} ");
    }

    protected function renderMessages(){
        $html = '';
        if($this->_messages)
            $html .= '<br><hr><br>';
            foreach ($this->_messages as $message){
                $html .= $message.PHP_EOL;
            }
        return $html;
    }

    protected function render()
    {

        $html = '
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Find/Replace</title>
</head>
<body>';
        $html .= $this->renderForm();
        $html .= $this->renderMessages();
        $html .= '</body>
</html>';

        echo $html;

    }
}

$app = new App();
$app->run();