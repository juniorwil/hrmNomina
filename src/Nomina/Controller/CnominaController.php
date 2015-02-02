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
use Principal\Model\IntegrarFunc; // Funciones para integrar nomina

use Nomina\Model\Entity\Gnominac; // Procesos especiales apra generacion de nomina

class CnominaController extends AbstractActionController
{
    public function indexAction()
    {
        return new ViewModel();
    }
    private $lin  = "/nomina/cnomina/list"; // Variable lin de acceso  0 (C)
    private $tlis = "Cierre de nominas activas"; // Titulo listado
    private $tfor = "Cierre de nomina"; // Titulo formulario
    private $ttab = "Tipo de nomina, Periodo, Tipo de calendario, Grupo ,Cerrar nomina"; // Titulo de las columnas de la tabla
    
    // Listado de registros ********************************************************************************************
    public function listAction()
    {
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d=new AlbumTable($this->dbAdapter);
      $valores=array
      (
        "titulo"    =>  $this->tlis,
        "datos"     =>  $d->getGeneral("select a.id,a.fechaI,a.fechaF,b.nombre as nomgrup, c.nombre as nomtcale, d.nombre as nomtnom,a.estado
                                        from n_nomina a inner join n_grupos b on a.idGrupo=b.id 
                                        inner join n_tip_calendario c on a.idCal=c.id 
                                        inner join n_tip_nom d on d.id=a.idTnom where a.estado=1"),            
        "ttablas"   =>  $this->ttab,
        "lin"       =>  $this->lin
      );                
      return new ViewModel($valores);
        
    } // Fin listar registros  

   //----------------------------------------------------------------------------------------------------------
   // CIERRE DE NOMINA --------------------------------------------------------------------------------------
   //----------------------------------------------------------------------------------------------------------
    public function listpAction()
    {
      $id = (int) $this->params()->fromRoute('id', 0);
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d=new AlbumTable($this->dbAdapter);            
      // INICIO DE TRANSACCIONES
      $connection = null;
      try {      
         $connection = $this->dbAdapter->getDriver()->getConnection();
		$connection->beginTransaction();          
          // Cambiar estado de nomina
          $con2 = 'update n_nomina set estado=2 where id='.$id ;     
          $d->modGeneral($con2);           
          // Consulta del tipo de nomina 
          $datos = $d->getGeneral1("Select * from n_nomina where id=".$id); 
          $fechaI = $datos['fechaI'];
          $fechaF = $datos['fechaF'];
          // Se activa el tipo nomina      
          $con2 = 'update n_tip_nom set activa=0 where id='.$datos['idTnom'];           
          $d->modGeneral($con2);                                     
          // Cerrar novedades 
          $con2 = "update n_novedades a 
               inner join n_tip_calendario_d b on b.id = a.idCal
               set a.estado=1 where '".$fechaI."'>=b.fechaI and b.fechaF<='".$fechaF."'" ;     
          $d->modGeneral($con2);                                           
          //---------------------------------------------------------
          $c=new Gnominac($this->dbAdapter);
          // Verificar en movimiento del calendario
          $datos2 = $c->getRegistroId( $datos['idTnom'] ,$datos['idGrupo'] , $datos['idCal']);                                               
          // Mover calendario 
          $c->actRegistro( $datos['idTnom'] , $datos['idGrupo'] , $datos['idCal'] ,$datos['fechaI'] , $datos['fechaF'] ,1,1 );                               
          // Registrar descuentos de pagos
          $d->modGeneral("update n_prestamos_tn a 
                          inner join n_nomina_e_d b on b.idCpres=a.id
                          set a.pagado = a.pagado + b.deducido    
                          where b.idNom=".$id);                                                      
          // Registrar pago de vacaciones
          $d->modGeneral("update n_vacaciones a 
                          inner join n_nomina_e b on b.idVac=a.id 
                          set a.estado = 2 
                          where b.idNom=".$id);                                                            
          // Activar salida a vacaciones del empleado
          $d->modGeneral("update a_empleados a 
                          inner join n_nomina_e b on b.idEmp=a.id and b.idVac=a.idVac
                          set a.vacAct = 1
                          where a.vacAct = 0 and b.idVac>0 and b.idNom=".$id);                                                            
          // Activar regreso a vacaciones del empleado
          $d->modGeneral("update a_empleados a 
                         inner join n_nomina_e b on b.idEmp=a.id and b.idVac=a.idVac
                         set a.vacAct = 0
                         where a.vacAct = 2 and b.idVac>0 and b.idNom=".$id);                                                                  
          // Activar regreso de incapacidad empleado pagos por nomina
          $d->modGeneral("update a_empleados c 
                         inner join n_nomina_e_i a on a.idEmp = c.id 
                         inner join n_nomina b on b.id=a.idNom 
                         inner join n_incapacidades d on d.id=a.idInc 
                         inner join n_tipinc e on e.id = d.idInc  
                         set c.idInc=0   
                         where e.completa=0 and a.idNom = ".$id." and a.idInc>0 and d.fechaf <= b.fechaF  ");// Si la fecha fin de incapacidad es menor que 
                         //la fecha fin de nomina sale de la incapacidad          
                         
          // Activar regreso de incapacidad empleado pago completo (Eje Maternidad)
          $d->modGeneral("update a_empleados c 
                         inner join n_nomina_e_i a on a.idEmp = c.id 
                         inner join n_nomina b on b.id=a.idNom 
                         inner join n_incapacidades d on d.id=a.idInc 
                         inner join n_tipinc e on e.id = d.idInc  
                         set c.idInc=0   
                         where e.completa=1 and a.idNom = ".$id." and a.idInc>0 ");// Si la fecha fin de incapacidad es menor que 
                         //la fecha fin de nomina sale de la incapacidad          

          // Reportar incapacidades
          $d->modGeneral("update n_incapacidades a
inner join n_nomina_e_i b on b.idInc = a.id set a.reportada=1 where  b.idNom=".$id);                                                                      

          // Activar grupo 
          $d->modGeneral("update n_grupos a
             inner join n_nomina b on b.idGrupo = a.id
             set a.activa=0 where  b.id =".$id);                                                                      

          
          // Cerrar cesantias
          $d->modGeneral("update n_cesantias set estado = 2 where idNom=".$id);                                                                      
          // Cerrar primas
          $d->modGeneral("update n_primas set estado = 2 where idNom=".$id);                                                                                
          
          // Integrar nomina
          $c = new IntegrarFunc($this->dbAdapter);
          $c->getIntegrarNomina($id); 
          
          $connection->commit();
          return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin);
          
        }// Fin try casth   
        catch (\Exception $e) {
    	      if ($connection instanceof \Zend\Db\Adapter\Driver\ConnectionInterface) {
     	           $connection->rollback();
                   echo $e;
 	       }	
 	       /* Other error handling */
       }// FIN TRANSACCION                          
       
       // return new ViewModel();        
    } // Fin generar novedades automaticas

   
}
