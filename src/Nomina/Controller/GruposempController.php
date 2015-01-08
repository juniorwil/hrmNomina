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
use Principal\Model\NominaFunc;        // Libreria de funciones nomina
use Nomina\Model\Entity\Novedades;       // (C)


class GruposempController extends AbstractActionController
{
    public function indexAction()
    {
        return new ViewModel();
    }
    private $lin  = "/nomina/gruposemp/list"; // Variable lin de acceso  0 (C)
    private $tlis = "Grupos, Convenciones u otros para empleados"; // Titulo listado
    private $tfor = "Grupos, Convenciones u otros para empleados"; // Titulo formulario
    private $ttab = "Grupos - Convenciones u otros, Personal"; // Titulo de las columnas de la tabla
    
    // Listado de registros ********************************************************************************************
    public function listAction()
    {
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d=new AlbumTable($this->dbAdapter);
      $valores=array
      (
        "titulo"    =>  $this->tlis,
        "datos"     =>  $d->getGeneral("select a.*, count(b.id)  as numItem 
                                        from n_tipemp a 
                                        left join n_tipemp_p b on b.idTemp = a.id group by a.id "),            
        "ttablas"   =>  $this->ttab,
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
      // GUARDAR NOVEDADES //
      if($this->getRequest()->isPost()) // Actualizar 
      {
        $request = $this->getRequest();
        if ($request->isPost()) {      
            $u    = new Novedades($this->dbAdapter);// ------------------------------------------------- 3 FUNCION DENTRO DEL MODELO (C)  
            $data = $this->request->getPost();
            $d->modGeneral("insert into n_tipemp_p (idEmp, idTemp, fecha)"
                    . " values(".$data->idEmp.",".$data->id.",'".$data->fecDoc."')");       
        }
      }
           
      // Empleados
      $arreglo='';
      $datos = $d->getEmp(''); 
      foreach ($datos as $dat){
         $idc=$dat['id'];$nom=$dat['CedEmp'].' - '.$dat['nombre'].' '.$dat['apellido'];
         $arreglo[$idc]= $nom;
      }              
      $form->get("idEmp")->setValueOptions($arreglo);                         
      
      // Buscar de en novedades cuales tienen formulas 
      $datos = $d->getGeneral1("select * from n_tipemp where id=".$id);      
      $valores=array
      (
           "titulo"  => "Listado de ".$datos['nombre'],
           "form"    => $form,
           'url'     => $this->getRequest()->getBaseUrl(),         
           'id'      => $id,          
           'datos'   => $d->getGeneral("select b.id, a.idTemp, a.fecing, 
                                          a.CedEmp, a.nombre, a.apellido, a.fecIng, b.fecha   
                                          from a_empleados a 
                                              inner join n_tipemp_p b on b.idEmp = a.id 
                                              where a.idTemp=".$id),
           "ttablas" =>  "Cedula, Nombres y apellidos, Fecha de ingreso empresa, Fecha ingreso grupo, Eliminar",                   
           "lin"     => $this->lin
      );       
      // ------------------------ Fin valores del formulario      
      return new ViewModel($valores);        

   } // Fin actualizar datos 
   // Eliminar dato ********************************************************************************************
   public function listidAction() 
   {
      $id = (int) $this->params()->fromRoute('id', 0);
      if ($id > 0)
         {
            $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
            // Buscar id del tipo del tipo de novedad
            $d=new AlbumTable($this->dbAdapter);
            $datos = $d->getGeneral1("select idTemp from n_tipemp_p where id = ".$id); 
            $d->modGeneral("delete from n_tipemp_p where id = ".$id); 
            
            return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin.'a/'.$datos['idTemp']);
          }          
   }   
   // Eliminar dato ********************************************************************************************
   public function listdAction() 
   {
      $id = (int) $this->params()->fromRoute('id', 0);
      if ($id > 0)
         {
            $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
            $u=new Novedades($this->dbAdapter);  // ---------------------------------------------------------- 5 FUNCION DENTRO DEL MODELO (C)         
            // Buscar id del tipo del tipo de novedad
            $d=new AlbumTable($this->dbAdapter);
            $datos = $d->getGeneral1("select c.id from n_novedades a 
                                        inner join n_tip_matriz c on c.id = a.idMatz 
                                        where a.id = ".$id); 
            //print_r($datos);
            $u->delRegistro($id);
            return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin.'a/'.$datos['id']);
          }          
   }   
   
}
