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
use Nomina\Model\Entity\Auto; // (C)
use Nomina\Model\Entity\Auton; // (C)


class AutoController extends AbstractActionController
{
    public function indexAction()
    {
        return new ViewModel();
    }
    private $lin  = "/nomina/auto/list"; // Variable lin de acceso  0 (C)
    private $tlis = "Automaticos de nominas por empleado"; // Titulo listado
    private $tfor = "Actualización de automaticos empleado"; // Titulo formulario
    private $ttab = "Cedula, Empleado, Cargo, Grupo, Automatico ,Conceptos"; // Titulo de las columnas de la tabla
//    private $mod  = "Nivel de aspecto ,A,E"; // Funcion del modelo
    
    // Listado de registros ********************************************************************************************
    public function listAction()
    {
      $form = new Formulario("form");        
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d=new AlbumTable($this->dbAdapter);      
      // Grupo de nomina
      $arreglo='';
      $datos = $d->getGrupo(); 
      $arreglo[0]= 'Ver todos';
      foreach ($datos as $dat){
         $idc=$dat['id'];$nom=$dat['nombre'];
         $arreglo[$idc]= $nom;
      }              
      $form->get("idGrupo")->setValueOptions($arreglo);                               
      // Ver si hay post para consultar
      $con = '';
      if($this->getRequest()->isPost()) 
      {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
            $u    = new Auto($this->dbAdapter);// ------------------------------------------------- 3 FUNCION DENTRO DEL MODELO (C)  
            $data = $this->request->getPost();
            $idGr = $data['idGrupo'];
            if ($idGr>0)
               {                
                 $con=' and a.idGrup = '.$idGr;
               }
            $busq = $data['nombre'];   
            if ($busq!='')
               {                
                 $con.="and (a.nombre like '%".$busq."%' or a.apellido like '%".$busq."%')";                 
               }               
        }
      }            
      $valores=array
      (
        "titulo"    =>  $this->tlis,
        "datos"     =>  $d->getGeneral("select distinct a.id,a.CedEmp, a.nombre as nomEmp,
                  a.apellido,b.nombre,
                  c.nombre as nomgrup, d.nombre as nomtau ,
		  count( e.id ) as conAut, case when f.id is null then 0 else 1 end as nom   
                  from a_empleados a 
		  inner join t_cargos b on a.idCar=b.id 
		  inner join n_grupos c on a.idGrup=c.id 
		  inner join n_tip_auto d on d.id=a.idTau 
		  left join n_emp_conc e on e.idEmp=a.id 
                  left join n_emp_conc_tn f on f.idEmCon = e.id 
		  where a.activo=0 ".$con." group by a.id order by a.nombre,a.apellido"),            
        "ttablas"   =>  $this->ttab,
        'url'       =>  $this->getRequest()->getBaseUrl(),
        "form"      =>  $form,
        "lin"       =>  $this->lin
      );                
      return new ViewModel($valores);
        
    } // Fin listar registros 

   //----------------------------------------------------------------------------------------------------------
   // FUNCIONES ADICIONALES GUARDADO DE ITEMS   
     
   // Listado de items de la etapa **************************************************************************************
   public function listiAction()
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
               $u  = new Auto($this->dbAdapter);// ------------------------------------------------- 3 FUNCION DENTRO DEL MODELO (C)  
               $data = $this->request->getPost();
               $id = $u->actRegistro($data,$id); // Trae el ultimo id de insercion en nuevo registro              
               // Agregar a los tipos de conceptos que afecta
               $f = new Auton($this->dbAdapter);
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
      
      $datos = $d->getCencos();// Listado de centros de costos
      $arreglo[0] = 'No aplica';
      foreach ($datos as $dat){
          $idc=$dat['id'];$nom=$dat['nombre'];
          $arreglo[$idc]= $nom;
      }      
      $form->get("idCencos")->setValueOptions($arreglo);        
      $form->get("idCencos")->setValue(0);        

      $datos = $d->getTnom('');// Listado de tipos de nomina
      $arreglo='';
      foreach ($datos as $dat){
          $idc=$dat['id'];$nom=$dat['nombre'];
          $arreglo[$idc]= $nom;
      }     
      $form->get("idTnomm")->setValueOptions($arreglo);       
      
      $datos = $d->getGeneral1("Select CedEmp, nombre, apellido from a_empleados where id=".$id);
      
      $u=new Auto($this->dbAdapter); // ---------------------------------------------------------- 1 FUNCION DENTRO DEL MODELO (C)
      $valores=array
      (
           "titulo"    =>  'Conceptos automaticos ',
           "empleado"  =>  $datos['CedEmp'].' - '.$datos['nombre'].' '.$datos['apellido'],
           "datos"     =>  $d->getGeneral("select a.id, b.nombre, 
                                       case a.horasCal when 1 then 'Horas del calendario' 
                                       when 0 then a.valor end as horas, case a.cCosEmp when 1 then 'Centro de costo empleado' 
                                       when 0 then c.nombre end as nomCcos, d.id as idCa , e.nombre as nomTnom 
                                       from n_emp_conc a 
				       inner join n_conceptos b on a.idCon=b.id
                                       inner join n_cencostos c on a.idCcos=c.id 
                                       left join n_emp_conc_tn d on d.idEmCon = a.id 
                                       left join n_tip_nom e on e.id = d.idTnom 
                                       where a.idEmp=".$id),// Listado de formularios            
           "ttablas"   =>  'Conceptos, Centro de costo, Tipo de nomina,  Horas/Valor , Eliminar',
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
            //$u=new Auto($this->dbAdapter);  // ---------------------------------------------------------- 5 FUNCION DENTRO DEL MODELO (C)                    
            //$u->delRegistro($id);
            return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin.'i/'.$datos['idEmp']);
          }          
   }// Fin eliminar datos    
    
}
