<?php
// Clase para manejar privilegios
class auth {
    // Comprueba que haya una sesión iniciada con un rol | Útil para mostrar/ocultar contenido
    public static function has_role($role) {
        return isset($_SESSION['role']) && $_SESSION['role'] === $role;
    }
    
    // Requiere al usuario que tenga un rol, especificado en un array(ejem: ['admin','moderator'])
    // y salta a index en caso de no cumplir el requisito | Útil para denegar funciones
    public static function needs_role(array $role){
            if(isset($_SESSION['role'])){
                foreach($role as $possibleRole){
                    if($_SESSION['role'] === $possibleRole){
                        return;
                    }
                }
            }
            $_SESSION['error'] = "Acceso Denegado - No tienes el permiso para acceder ahi";
                header('Location: ../../index.php');
        }
}
