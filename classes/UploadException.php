<?php
/*
UploadException  =  "this picture was refused, and here is a sentence we can show to the visitor".

Our own exception type. The message is always written by us (never copied from the file or from PHP),
so the controller can show it on the page without escaping surprises. Anything else that goes wrong
(a real bug, a full disk) is a normal Exception and gets a general "something went wrong" message instead.
*/
class UploadException extends Exception
{
}
