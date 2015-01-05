<?php
/** STANDAR MAESTROS NISSI  */
// (C): Cambiar en el controlador 
namespace Nomina\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\Db\Adapter\Adapter;
use Zend\Form\Annotation\AnnotationBuilder;

use Principal\Form\Formulario;         // Componentes generales de todos los formularios
use Principal\Model\ValFormulario;     // Validaciones de entradas de datos
use Principal\Model\AlbumTable;        // Libreria de datos
use Nomina\Model\Entity\Asalarial;       // (C)
use Nomina\Model\Entity\AsalarialD;       // (C)

class AsalarialController extends AbstractActionController
{
    public function indexAction()
    {
        return new ViewModel();
    }
    private $lin  = "/nomina/asalarial/list"; // Variable lin de acceso  0 (C)
    private $tlis = "Incremento salarial"; // Titulo listado
    private $tfor = "Incremento salarial"; // Titulo formulario
    private $ttab = "Documento, Fecha , Estado, Edición,Eliminar"; // Titulo de las columnas de la tabla
    
    // Listado de registros ********************************************************************************************
    public function listAction()
    {
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d=new AlbumTable($this->dbAdapter);
      $valores=array
      (
        "titulo"    =>  $this->tlis,
        "daPer"     =>  $d->getPermisos($this->lin), // Permisos de usuarios
        "datos"     =>  $d->getGeneral("select id, fecDoc, estado from n_asalarial order by fecDoc desc"),            
        "ttablas"   =>  $this->ttab,
        "flashMessages" => $this->flashMessenger()->getMessages(), // Mensaje de guardado          
        "lin"       =>  $this->lin
      );                
      return new ViewModel($valores);
        
    } // Fin listar registros 
    
   // Editar y nuevos datos *********************************************************************************************
   // Tipo seleccion
   public function listaAction() 
   { 
      $form = new Formulario("form");
      //  valores iniciales formulario   (C)
      $id = (int) $this->params()->fromRoute('id', 0);
      $form->get("id")->setAttribute("value",$id); 
      
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d=new AlbumTable($this->dbAdapter);
      $form->get("estado")->setValueOptions(array("0"=>"Revisión","1"=>"Aprobado"));                                 
      $datos = $d->getGeneral("select id from n_salarios order by salario");

      // Guardar datos 
      if($this->getRequest()->isPost()) // Actualizar 
      {
        $request = $this->getRequest();
        if ($request->isPost()) {
      
            $u    = new Asalarial($this->dbAdapter);// ------------------------------------------------- 3 FUNCION DENTRO DEL MODELO (C)  
            $data = $this->request->getPost();      
            if ($data->id==0)
               $id = $u->actRegistro($data); // Trae el ultimo id de insercion en nuevo registro              
            else 
            {
               $u->actRegistro($data);             
               $id = $data->id;
            }            
            $u    = new AsalarialD($this->dbAdapter);// ------------------------------------------------- 3 FUNCION DENTRO DEL MODELO (C)  
            $d->modGeneral("delete from n_asalarial_d where idAsal=".$id);            
            //echo $data->sa1.' - ';
//            print_r($data);
            foreach ($datos as $dato){    
            {       
                
                $idP  = (int) $dato['id'];
                
                $sal  = '$data->sa'.$idP;
                eval("\$sal =$sal;"); 
                
                $por = '$data->'.$idP;
                eval("\$por =$por;"); 
                
                $salA = '$data->nsa'.$idP;                
                eval("\$salA =$salA;"); 
                
                $u->actRegistro($data,$id,$idP,$sal,$por,$salA);             
            }
            $this->flashMessenger()->addMessage('');
            return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin);// El 1 es para mostrar mensaje de guardado
      
         }
        }                 
      }
      $valores=array
      (
           "titulo"  => $this->tfor,
           "form"    => $form,
           'url'     => $this->getRequest()->getBaseUrl(),           
           'datos'   => $d->getGeneral("select a.* from n_salarios a order by a.salario "),
           "lin"     => $this->lin, 
           "ttablas"  => "Codigo, Salario actual, % Incremento, Nuevo salario "
      );       
      // ------------------------ Fin valores del formulario      
      return new ViewModel($valores);        

     } // Fin actualizar datos  
   
   
   // Eliminar dato ********************************************************************************************
   public function listedAction() 
   {
      $id = (int) $this->params()->fromRoute('id', 0);
      if ($id > 0)
         {
            $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
            $u=new Novedades($this->dbAdapter);  // ---------------------------------------------------------- 5 FUNCION DENTRO DEL MODELO (C)         
            // Buscar id del tipo del tipo de novedad
            $d=new AlbumTable($this->dbAdapter);
            $datos = $d->getGeneral1("select c.id from n_novedades a 
                                      inner join n_tip_matriz_tnv b on b.id=a.idTmatz 
                                      inner join n_tip_matriz c on c.id=b.idTmatz
                                      where a.id=".$id);             
            $u->delRegistro($id);
            return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin.'a/'.$datos['id']);
          }          
   }   



   
}
