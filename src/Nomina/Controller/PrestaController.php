<?php
/** STANDAR MAESTROS NISSI  */
// (C): Cambiar en el controlador 
namespace Nomina\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\Db\Adapter\Adapter;
use Zend\Form\Annotation\AnnotationBuilder;

use Nomina\Model\Entity\Presta;     // (C)
use Nomina\Model\Entity\Prestan;     // (C)

use Principal\Form\Formulario;      // Componentes generales de todos los formularios
use Principal\Model\ValFormulario;  // Validaciones de entradas de datos
use Principal\Model\AlbumTable;     // Libreria de datos

use Principal\Model\NominaFunc;        

class PrestaController extends AbstractActionController
{
    public function indexAction()
    {
        return new ViewModel();
    }
    private $lin  = "/nomina/presta/list"; // Variable lin de acceso  0 (C)
    private $tlis = "Prestamos a empleados"; // Titulo listado
    private $tfor = "Documento de solicitud"; // Titulo formulario
    private $ttab = "id, Empleado,Fecha, Fecha aprobación, Tipo de prestamo,Estado,Pdf,Cargo,Centro de costos,Valor,Editar,Eliminar"; // Titulo de las columnas de la tabla

    // Listado de registros ********************************************************************************************
    public function listAction()
    {
        
        $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
        $u=new AlbumTable($this->dbAdapter);
        $valores=array
        (
            "titulo"    =>  $this->tlis,
            "daPer"     =>  $u->getPermisos($this->lin), // Permisos de usuarios
            "datos"     =>  $u->getGeneral("select a.id, a.fecDoc,fecApr,a.valor,b.nombre,b.apellido,b.CedEmp, 
                                            c.nombre as nomcar, d.nombre as nomccos, a.estado
                                            , e.nombre as nomTpres
                                            from n_prestamos a inner join a_empleados b on a.idEmp=b.id 
                                            left join t_cargos c on c.id=b.idCar
                                            inner join n_cencostos d on d.id=b.idCcos
                                            inner join n_tip_prestamo e on e.id = a.idTpres 
                                            order by a.fecDoc desc "),            
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
      $d = New AlbumTable($this->dbAdapter);      
      // Tipos de nomina
      $arreglo='';
      $datos = $d->getTnom('');
      foreach ($datos as $dat)
      {
        $idc=$dat['id'];$nom=$dat['nombre'];
        $arreglo[$idc]= $nom;
      }      
      $form->get("idTnom")->setValueOptions($arreglo);                    
      // Empleados     
      $arreglo='';
      $datos = $d->getEmp('');
      foreach ($datos as $dat)
      {
        $idc=$dat['id'];$nom=$dat['CedEmp'].' - '.$dat['nombre'].' '.$dat['apellido'];
        $arreglo[$idc]= $nom;
      }      
      $form->get("idEmp")->setValueOptions($arreglo);              
      $arreglo='';
      $datos = $d->getTpres("");
      foreach ($datos as $dat)
      {
        $idc=$dat['id'];$nom=$dat['nombre'];
        $arreglo[$idc]= $nom;
      }      
      $form->get("idTpres")->setValueOptions($arreglo);                    
      
      $arreglo='';
      $datos = $d->getEntidades();
      foreach ($datos as $dat)
      {
        $idc=$dat['id'];$nom=$dat['nombre'];
        $arreglo[$idc]= $nom;
      }      
      $form->get("idEnt")->setValueOptions($arreglo);                          
      
      $datTnom = $d->getGeneral1("select idTpres, estado from n_prestamos where id=".$id);
      // Estado
      $daPer = $d->getPermisos($this->lin); // Permisos de esta opcion
      if ($datTnom['estado']==0)
      { 
         $val=array
         (
            "0"  => 'Revisión',
            "1"  => 'Aprobado'              
          );                 
      }else{
         $val=array
         (
            "1"  => 'Aprobado',
            "3"  => 'Inactivo',             
          );                             
      }
      $form->get("estado")->setValueOptions($val);
      $valores=array
      (
           "titulo"  => $this->tfor,
           "form"    => $form,
           'datTnom' => $d->getPresCuotas($datTnom['idTpres'], $id),     
           'url'     => $this->getRequest()->getBaseUrl(),
           'id'      => $id,
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
                $u    = new Presta($this->dbAdapter);// ------------------------------------------------- 3 FUNCION DENTRO DEL MODELO (C)  
                $data = $this->request->getPost();
                // Buscar datos actuales del empleado
                $dat = $d->getGeneral1("Select idCar, idCcos from a_empleados where id=".$data->idEmp);
                // Buscar tipo de nomina en tipo de prestamo
                $dat2 = $d->getGeneral1("Select idTnom from n_tip_prestamo where id=".$data->idTpres);                
                // INICIO DE TRANSACCIONES
                $connection = null;
                try {
                    $connection = $this->dbAdapter->getDriver()->getConnection();
   	                $connection->beginTransaction();                
                    
                    $idPres = $u->actRegistro($data, $dat['idCar'], $dat['idCcos'], $dat2['idTnom'] );
                    
                    ////// Guardar distribucion de pagos en tipos de nominas //// ---
                    $datTnom = $d->getGeneral1("select idTpres from n_prestamos where id=".$idPres);                    

                    //$d->modGeneral("Delete from n_prestamos_tn where idPres=".$idPres);                 
                    $datos = $d->getPresCuotas($datTnom['idTpres'], $idPres );
                    $f    = new Prestan($this->dbAdapter);
                    $valorT = 0;
                    foreach ($datos as $dato){ 
                        $idLc = $dato['idTnom'];
                        $texto = '$data->valor'.$idLc;                        
                        eval("\$valor = $texto;"); 

                        if ($valor > 0) 
                        {
                            $texto = '$data->cuotas'.$idLc;                        
                            eval("\$cuotas = $texto;");                        
                            // Consultar pagos y saldos iniciales del tipo de prestamo
                            $datTnomPg = $d->getGeneral1("select count(id) as num from n_prestamos_tn where idPres=".$id." and idTnom=".$idLc); 
                            if ( $datTnomPg['num'] == 0 )
                               $f->actRegistro( $idPres,$idLc,$valor,$cuotas );                       
                            else
                               $d->modGeneral("update n_prestamos_tn 
                                   set valor=".$valor.", cuotas=".$cuotas.", valCuota =".($valor / $cuotas)." where idPres=".$id." and idTnom=".$idLc);    
                            $valorT = $valorT + $valor;  
                        }
                    }                
                    $d->modGeneral("update n_prestamos set valor=".$valorT." where id=".$idPres);                 
                    $connection->commit();
                    $this->flashMessenger()->addMessage('');
                    return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin.'a/'.$idPres);
                }// Fin try casth   
                catch (\Exception $e) {
    	            if ($connection instanceof \Zend\Db\Adapter\Driver\ConnectionInterface) {
     	                $connection->rollback();
                        echo $e;
 	            }	
 	            /* Other error handling */
                }// FIN TRANSACCION                                                        
            }
        }
        return new ViewModel($valores);
        
    }else{              
      if ($id > 0) // Cuando ya hay un registro asociado
         {
            $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
            $u=new Presta($this->dbAdapter); // ---------------------------------------------------------- 4 FUNCION DENTRO DEL MODELO (C)          
            $datos = $u->getRegistroId($id);
            // Valores guardados
            $form->get("comen")->setAttribute("value",$datos['comen']); 
            $form->get("numero")->setAttribute("value",$datos['valor']); 
            $form->get("idEmp")->setAttribute("value",$datos['idEmp']); 
            $form->get("idTpres")->setAttribute("value",$datos['idTpres']); 
            $form->get("idTnom")->setAttribute("value",$datos['idTnom']); 
            $form->get("estado")->setAttribute("value",$datos['estado']); 
            $form->get("nombre")->setAttribute("value",$datos['docRef']); 
            $form->get("fecDoc")->setAttribute("value",$datos['fecDref']);             
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
            $u=new AlbumTable($this->dbAdapter);
            $u->modGeneral("delete from n_prestamos_tn where idPres=".$id);                        
            $u=new Presta($this->dbAdapter);  // ---------------------------------------------------------- 5 FUNCION DENTRO DEL MODELO (C)         
            $u->delRegistro($id);
            return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin);
          }          
   }      
   
   // VALIDACION DEL PERIODO PARA GUARDADO DE DATOS
   public function listgAction() 
   {
      $form = new Formulario("form");  
      $request = $this->getRequest();
      if ($request->isPost()) {
            $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
            $u=new AlbumTable($this->dbAdapter);  // ---------------------------------------------------------- 5 FUNCION DENTRO DEL MODELO (C)         
            $data = $this->request->getPost();       
            $datos = $u->getGeneral1("select idGrup from a_empleados where id=".$data->idEmp);            
            $idGrup = $datos['idGrup'];
            $datos = $u->getGeneral1("select a.idTnom, b.idTcal from n_tip_prestamo a 
                        inner join n_tip_nom b on b.id=a.idTnom
                        where a.id=".$data->idTpres);
            // Buscar datos del periodo
            $datos = $u->getCalenIniFin2($idGrup, $datos['idTcal'], $datos['idTnom']); 
            $arreglo = '';
            foreach ($datos as $dat){
                $idc=$dat['id'];$nom=$dat['fechaI'].' - '.$dat['fechaF'];
                $arreglo[$idc]= $nom;
                break; 
            }  
            // Comprar el periodo que se intenta guardar
            $date   = new \DateTime(); 
            $fecSis = $date->format('Y-m-d');        
            $sw = 0;
            // Fecha del sistema
            $fechaI = $dat['fechaI'];
            $valido = 0;
            if ($fecSis < $fechaI ) // Si es menor que la fecha del sistema no debe guardar el documento
                $valido = 1;
            
            $valores = array(
               "verPer" => $valido,
               "form"   => $form, 
            );                    
            $view = new ViewModel($valores);        
            $this->layout("layout/blancoC");
            return $view;
      }      
   }      

}
