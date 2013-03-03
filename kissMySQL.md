kissMySQL
=========

A slightly simpler to use version of the MySQL version of ezSQL (http://justinvincent.com/ezsql)

##Setup

Call a new instance of kissMySQL with your database credentials.

    $db = new kissMySQL('mydatabase_user', 'password', 'mydatabase');

There are 3 additional arguments you cab pass:
- $host; default is 'localhost'
- $charset; default is 'utf8'
- $collate; default is 'utf8_general_ci'

##Queries

To execute a query, call the query method, passing the query statement, and optionally the variables to be instered into the placeholders.

    $db->query("INSERT INTO mytable (mycolumn) VALUES (%s)", $myvalue);

You can pass the variables as either separate arguments or as a numeric array of them. All queries are automatically prepared with the passed additional arguments. The preparation method supports argument numbering/swapping ('%1$s' instead of '%s').

##Shortcut Methods

Like ezSQL, kissMySQL includes a few functions for specifically fetching an array of results, or just a row/column/variable.

    $db->get_results("SELECT * FROM mytable WHERE mycolumn = %s", $myvalue, ARRAY_A);
    
    $db->get_row("SELECT * FROM mytable WHERE mycolumn = %s", $myvalue, ARRAY_A);
    
    $db->get_col("SELECT column FROM mytable WHERE mycolumn = %s", $myvalue);
    
    $db->get_var("SELECT column FROM mytable WHERE mycolumn = %s", $myvalue);

For get_results/row, you can specify the output type (OBJECT, OBJECT_K, ARRAY_A, ARRAY_N) before or after the variables.

If you need to get a specific row/column/variable from a result, you can use the following methods:

    $db->get_row_y($query, ARRAY_A, 5);
    
    $db->get_col_x($query, 3);
    
    $db->get_var_x_y($query, 3, 5);

There are also shortcut methods for insert, replace, update and delete queries.

    $db->insert(
        'mytable',
        array(
            'mycolumn' => $myvalue
        ),
        array(
            '%s'
        )
    );
    
    $db->update(
        'mytable',
        array(
            'mycolumn' => $myvalue
        ),
        array(
            'ID' => $id
        ),
        array(
            '%s'
        ),
        array(
            '%d'
        )
    );
    
    $db->delete(
        'mytable',
        array(
            'ID' => $id
        ),
        array(
            '%d'
        )
    );

So, yeah, pretty much the same usage as ezMySQL, however you no longer have to do this:

    $db->query($db->prepare("SELECT * FROM mytable WHERE mycolumn = %s", $myvalue));

In other words, I got paradoxically lazy to the point that I spent the time to rework an abstraction class to let me do stuff without needing an extra 14 or so characters to do it.
