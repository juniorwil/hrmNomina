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



class RetefuenteController extends AbstractActionController
{
    public function indexAction()
    {
        return new ViewModel();
    }
    private $lin  = "/nomina/retefuente/list"; // Variable lin de acceso  0 (C)
    private $tlis = "Empleados con rete fuente"; // Titulo listado
    private $tfor = "Actualización de Retefuentematicos empleado"; // Titulo formulario
    private $ttab = "Cedula, Empleado, Tipo, Porcentaje ,Items ,Eliminar"; // Titulo de las columnas de la tabla
//    private $mod  = "Nivel de aspecto ,A,E"; // Funcion del modelo    

     
   // Listado de items de la etapa **************************************************************************************
   public function listAction()
   {
      $form = new Formulario("form");
      $id = (int) $this->params()->fromRoute('id', 0);
      $form->get("id")->setAttribute("value",$id);
      $form->get("numero")->setAttribute("value",0);
      $form->get("check2")->setAttribute("value",1);
      if($this->getRequest()->isPost()) 
      {
        $request = $this->getRequest();
        if ($request->isPost()) {
            // Zona de validacion del fomrulario  --------------------
            $album = new ValFormulario();
            $form->setInputFilter($album->getInputFilter());            
            $form->setData($request->getPost());           
            $form->setValidationGroup('numero'); // ------------------------------------- 2 CAMPOS A VALDIAR DEL FORMULARIO  (C)            
           // Fin validacion de formulario ---------------------------
            if ($form->isValid()) {
               $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
               $u  = new Retefuente($this->dbAdapter);// ------------------------------------------------- 3 FUNCION DENTRO DEL MODELO (C)  
               $data = $this->request->getPost();
               $id = $u->actRegistro($data,$id); // Trae el ultimo id de insercion en nuevo registro              
               // Agregar a los tipos de conceptos que afecta
               $f = new Retefuenten($this->dbAdapter);
               foreach ($data->idTnomm as $dato){
                  $idTnom = $dato[0];                      
                  $f->actRegistro($idTnom,$id);                
                }                
               return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin.'i/'.$data->id);
               //               
            } 
        }
      } 
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');      
      $d = New AlbumTable($this->dbAdapter);      
      
      $datos = $d->getConnom();// Listado de conceptos
      $arreglo = '';
      foreach ($datos as $dat){
          if ($dat['valor']==1)
              $valor='HORAS'; else $valor='PESOS'; 
          $idc=$dat['id'];$nom=$dat['nombre'].' ('.$valor.')';
          $arreglo[$idc]= $nom;
      }      
      $form->get("tipo")->setValueOptions($arreglo);  
      
      $datos = $d->getEmp("");// Listado de empleados
      $arreglo = '';
      foreach ($datos as $dat){
          $idc=$dat['id'] ; $nom = $dat['CedEmp'].' - '.$dat['nombre'].' '.$dat['apellido'];
          $arreglo[$idc]= $nom;
      }      
      $form->get("idEmp")->setValueOptions($arreglo);        
      
      $form->get("tipo")->setValueOptions(array( "1"=>"Anual (Procedimiento 1)", "2"=>"Mensual (Procedimiento 2)" ));       
   
      if($this->getRequest()->isPost()) // Actulizar datos
      {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
            $d    = new AlbumTable($this->dbAdapter);// ------------------------------------------------- 3 FUNCION DENTRO DEL MODELO (C)  
            $data = $this->request->getPost();				
            $d->modGeneral("insert into a_empleados_rete (idEmp, tipo) values(".$data->idEmp.",".$data->tipo." )");  

            
        }       
      } 

      $valores=array
      (
           "titulo"    =>  'Empleados con retención en la fuente ',
           "datos"     =>  $d->getGeneral("select a.id, a.porcentaje, case when a.tipo=1 then 'Anual (Procedimiento 1)' else 'Mensual (Procedimiento 2)' end as tipo,
                                 b.CedEmp, b.nombre, b.apellido  
                                 from a_empleados_rete a 
                                 inner join a_empleados b on b.id = a.idEmp"),// Listado de formularios            
           "ttablas"   =>  $this->ttab,
           'url'       =>  $this->getRequest()->getBaseUrl(),
           "form"      =>  $form,
           "lin"       =>  $this->lin
       );                
       return new ViewModel($valores);        
   } // Fin listar registros items

   // Conceptos de la etapa **************************************************************************************
   public function listiAction()
   {
      $form = new Formulario("form");
      $id = (int) $this->params()->fromRoute('id', 0);
      $form->get("id")->setAttribute("value",$id);
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');      
      $d = New AlbumTable($this->dbAdapter);           	  
      if($this->getRequest()->isPost()) 
      {
        $request = $this->getRequest();
        if ($request->isPost()) {
            // Zona de validacion del fomrulario  --------------------
            $data = $this->request->getPost();
            $datos = $d->getGeneral1("select b.id  
                          from  a_empleados_rete a 
                          inner join a_empleados b on b.id = a.idEmp where a.id=".$data->id);
			$idEmp = $datos["id"];			  			
 
			$d->modGeneral("delete from a_empleados_rete_d where idEmp=".$idEmp);
			
            $datos =  $d->getGeneral("select * from n_rete_conc  ");// Listado de formularios                
            
            foreach($datos as $datDes)
            {
            	if ($datDes['tipo']==1)// Check                                
                   $var = '$data->ch'.$datDes['id'];
				
            	if ($datDes['tipo']==2)// Numero                                
                   $var = '$data->nu'.$datDes['id'];				
				
                eval("\$valor=$var;");
				//echo $valor.'d ';                                                                 
                if ($valor>0) // Guardar traslado
                {
                    $d->modGeneral("insert into a_empleados_rete_d (idEret, idEmp , idCret, valor) 
                    values(".$data->id.", ".$idEmp.", ".$datDes['id'].",".$valor."  )");                    
                }                     
            }
            return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin.'i/'.$data->id);
               //               
        }
      } 

      $datos = $d->getGeneral1("select b.id, b.CedEmp, b.nombre, b.apellido 
                          from  a_empleados_rete a 
                          inner join a_empleados b on b.id = a.idEmp where a.id=".$id);
      $valores=array
      (
           "titulo"    =>  'Alivios retención en la fuente '.$datos['CedEmp'].' - '.$datos['nombre'].' '.$datos['apellido'],
           "datos"     =>  $d->getGeneral("select a.*, 
                                  case when b.valor is null then 0 else b.valor end as valor 
                                  from n_rete_conc a
                                  left join a_empleados_rete_d b on b.idCret = a.id and b.idEret = 1"),// Listado de formularios            
           "ttablas"   =>  $this->ttab,
           'url'       =>  $this->getRequest()->getBaseUrl(),
           "form"      =>  $form,
           "lin"       =>  $this->lin
       );                
       return new ViewModel($valores);        
   } // Fin listar registros items   
   
   // Eliminar dato ********************************************************************************************
   public function listidAction() 
   {
      $id = (int) $this->params()->fromRoute('id', 0);
      if ($id > 0)
         {
            $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
            $d=new AlbumTable($this->dbAdapter);

            $datos = $d->getGeneral1("select idEmp from n_emp_conc where id=".$id);             
            $d->modGeneral("delete from n_emp_conc_tn where idEmCon=".$id);                     
            $d->modGeneral("delete from n_emp_conc where id=".$id);                     
            //$u=new Retefuente($this->dbAdapter);  // ---------------------------------------------------------- 5 FUNCION DENTRO DEL MODELO (C)                    
            //$u->delRegistro($id);
            return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin.'i/'.$datos['idEmp']);
          }          
   }// Fin eliminar datos    
    
}
