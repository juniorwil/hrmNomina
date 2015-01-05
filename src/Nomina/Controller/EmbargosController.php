<?php
/** STANDAR MAESTROS NISSI  */
// (C): Cambiar en el controlador 
namespace Nomina\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\Db\Adapter\Adapter;
use Zend\Form\Annotation\AnnotationBuilder;

use Nomina\Model\Entity\Embargos;     // (C)

use Principal\Form\Formulario;      // Componentes generales de todos los formularios
use Principal\Model\ValFormulario;  // Validaciones de entradas de datos
use Principal\Model\AlbumTable;     // Libreria de datos


class EmbargosController extends AbstractActionController
{
    public function indexAction()
    {
        return new ViewModel();
    }
    private $lin  = "/nomina/embargos/list"; // Variable lin de acceso  0 (C)
    private $tlis = "Embargos a empleados"; // Titulo listado
    private $tfor = "Documento de embargo"; // Titulo formulario
    private $ttab = "Fecha,Fec apro.,Empleado,Cargo,Centro de costos,Tipo,Estado, Pdf  ,Editar,Eliminar"; // Titulo de las columnas de la tabla

    // Listado de registros ********************************************************************************************
    public function listAction()
    {
        
        $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
        $u=new AlbumTable($this->dbAdapter);
        $valores=array
        (
            "titulo"    =>  $this->tlis,
            "daPer"     =>  $u->getPermisos($this->lin), // Permisos de usuarios
            "datos"     =>  $u->getGeneral("select a.*, b.CedEmp, b.nombre as nomEmp, b.apellido as nomApe, c.nombre as nomCar, d.nombre as nomCcos,
                                            e.nombre as nomTemb 
                                            from n_embargos a  
                                            inner join a_empleados b on b.id=a.idEmp
                                            inner join t_cargos c on c.id=b.idCar 
                                            inner join n_cencostos d on d.id=b.idCcos 
                                            inner join n_tip_emb e on e.id=a.idTemb 
                                            order by a.fecDoc desc"),            
            "ttablas"   =>  $this->ttab,
            "lin"       =>  $this->lin,
            "flashMessages" => $this->flashMessenger()->getMessages(), // Mensaje de guardado 
        );                
        return new ViewModel($valores);
        
    } // Fin listar registros 
    
 
   // Editar y nuevos datos *********************************************************************************************
   public function listaAction() 
   { 
      $form = new Formulario("form");
      //  valores iniciales formulario   (C)
      $id = (int) $this->params()->fromRoute('id', 0);
      $form->get("id")->setAttribute("value",$id);                       
      // Sedes
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      // Empleados
      $d = New AlbumTable($this->dbAdapter);      
      $datos = $d->getEmp('');
      $arreglo='';
      foreach ($datos as $dat)
      {
        $idc=$dat['id'];$nom=$dat['CedEmp'].' - '.$dat['nombre'].' '.$dat['apellido'];
        $arreglo[$idc]= $nom;
      }      
      $form->get("idEmp")->setValueOptions($arreglo);  
      // 
      $arreglo='';
      $datos = $d->getTemb(''); 
      foreach ($datos as $dat){
         $idc=$dat['id'];$nom=$dat['nombre'];
         $arreglo[$idc]= $nom;
      }              
      $form->get("tipo")->setValueOptions($arreglo);        
      //
      $datos = $d->getTerceros('');
      $arreglo='';
      foreach ($datos as $dat)
      {
        $idc=$dat['id'];$nom = $dat['nombre'];
        $arreglo[$idc]= $nom;
      }      
      $form->get("idTer")->setValueOptions($arreglo);        
      $datos = $d->getBancos('');
      $arreglo='';
      foreach ($datos as $dat)
      {
        $idc=$dat['id'];$nom = $dat['nombre'];
        $arreglo[$idc]= $nom;
      }      
      $form->get("idBanco")->setValueOptions($arreglo);              
      $datos=0;

      $val=array
          (
            "0"  => 'Revisión',
            "1"  => 'Aprobado',
            "2"  => 'Terminado'
          );       
      $form->get("estado")->setValueOptions($val);      
      
      $valores=array
      (
           "titulo"  => $this->tfor,
           "form"    => $form,
           'url'     => $this->getRequest()->getBaseUrl(),
           'id'      => $id,
           'datos'   => $datos,  
           "lin"     => $this->lin
      );       
      // ------------------------ Fin valores del formulario 
      
      if($this->getRequest()->isPost()) // Actulizar datos
      {
        $request = $this->getRequest();
        if ($request->isPost()) {
            // Zona de validacion del fomrulario  --------------------
            $album = new ValFormulario();
            $form->setInputFilter($album->getInputFilter());            
            $form->setData($request->getPost());           
            $form->setValidationGroup('id'); // ------------------------------------- 2 CAMPOS A VALDIAR DEL FORMULARIO  (C)            
            // Fin validacion de formulario ---------------------------
            if ($form->isValid()) {
                $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
                $u    = new Embargos($this->dbAdapter);// ------------------------------------------------- 3 FUNCION DENTRO DEL MODELO (C)  
                $data = $this->request->getPost();
                $u->actRegistro($data);
               
                $this->flashMessenger()->addMessage(''); 
                return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin);
            }
        }
        return new ViewModel($valores);
        
    }else{              
      if ($id > 0) // Cuando ya hay un registro asociado
         {
            $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
            $u=new Embargos($this->dbAdapter); // ---------------------------------------------------------- 4 FUNCION DENTRO DEL MODELO (C)          
            $datos = $u->getRegistroId($id);
            // Valores guardados
            $form->get("comen")->setAttribute("value",$datos['comen']); 
            $form->get("idEmp")->setAttribute("value",$datos['idEmp']); 
            $form->get("tipo")->setAttribute("value",$datos['idTemb']); 
            $form->get("estado")->setAttribute("value",$datos['estado']); 
            $form->get("formula")->setAttribute("value",$datos['formula']); 
            $form->get("numero")->setAttribute("value",$datos['valor']); 
            $form->get("idTer")->setAttribute("value",$datos['idTer']); 
            $form->get("formaPago")->setAttribute("value",$datos['idForP']); 
            $form->get("numCuenta")->setAttribute("value",$datos['numCuenta']); 
         }            
         return new ViewModel($valores);
      }
   } // Fin actualizar datos 
   
   // Eliminar dato ********************************************************************************************
   public function listdAction() 
   {
      $id = (int) $this->params()->fromRoute('id', 0);
      if ($id > 0)
         {
            $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
            $u=new Embargos($this->dbAdapter);  // ---------------------------------------------------------- 5 FUNCION DENTRO DEL MODELO (C)         
            $u->delRegistro($id);
            return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin);
          }          
   }

   
}
