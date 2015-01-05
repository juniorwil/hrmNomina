<?php
/** STANDAR MAESTROS NISSI  */
// (C): Cambiar en el controlador 
namespace Nomina\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use Zend\Db\Adapter\Adapter;
use Zend\Form\Annotation\AnnotationBuilder;

use Nomina\Model\Entity\Vacaciones;     // (C)
use Nomina\Model\Entity\VacacionesP;     // (C)

use Principal\Form\Formulario;      // Componentes generales de todos los formularios
use Principal\Model\ValFormulario;  // Validaciones de entradas de datos
use Principal\Model\AlbumTable;     // Libreria de datos
use Principal\Form\FormPres;        // Componentes especiales para los prestamos

class VacacionesController extends AbstractActionController
{
    public function indexAction()
    {
        return new ViewModel();
    }
    private $lin  = "/nomina/vacaciones/list"; // Variable lin de acceso  0 (C)
    private $tlis = "Registro de vacaciones "; // Titulo listado
    private $tfor = "Documento de vacaciones"; // Titulo formulario
    private $ttab = "Fecha,Cedula,Empleado,Cargo,Desde, Hasta ,Estado, Pdf, Editar,Eliminar"; // Titulo de las columnas de la tabla

    // Listado de registros ********************************************************************************************
    public function listAction()
    {            
        $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
        $u=new AlbumTable($this->dbAdapter); // ---------------------------------------------------------- 1 FUNCION DENTRO DEL MODELO (C)      
        $id = (int) $this->params()->fromRoute('id', 0);
        
        $valores=array
        (
            "titulo"    =>  $this->tlis,
            "daPer"     =>  $u->getPermisos($this->lin), // Permisos de usuarios
            "datos"     =>  $u->getSovac(" a.estado in ('0','1') "), // listado de vacaciones     
            "ttablas"   =>  $this->ttab,
            "lin"       =>  $this->lin,
            "id"        =>  $id,
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
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d=new AlbumTable($this->dbAdapter);
      // Empleados
      $arreglo='';
      $datos = $d->getEmp(''); 
      foreach ($datos as $dat){
         $idc=$dat['id'];$nom=$dat['CedEmp'].' - '.$dat['nombre'].' '.$dat['apellido'];
         $arreglo[$idc]= $nom;
      }              
      $form->get("idEmp")->setValueOptions($arreglo);  
      $form->get("estado")->setValueOptions(array("0"=>"Revisión","1"=>"Aprobado"));                           
      if ($id > 0) // Cuando ya hay un registro asociado
        {  
          $u=new Vacaciones($this->dbAdapter); // ---------------------------------------------------------- 4 FUNCION DENTRO DEL MODELO (C)          
          $datos = $u->getRegistroId($id);
          
          $form->get("idEmp")->setAttribute("value",$datos['idEmp'])
                             ->setAttribute("enabled",false);        
              
          $form->get("estado")->setAttribute("value",$datos['estado']);
        }      
      
      $valores=array
      (
           "titulo"  => $this->tfor,
           "form"    => $form,
           'url'     => $this->getRequest()->getBaseUrl(),
           "lin"     => $this->lin
      );       
      // ------------------------ Fin valores del formulario      
      return new ViewModel($valores);        

   } // Fin actualizar datos 
   
   public function listagAction() 
   { 
      $form = new Formulario("form");             
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d=new AlbumTable($this->dbAdapter);           
      
      if($this->getRequest()->isPost()) // Actualizar 
      {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $data = $this->request->getPost();              
            $u=new Vacaciones($this->dbAdapter); 
            if ($data->id>0)
            {
              $datos = $u->getRegistroId($data->id);
              $form->get("fecDoc")->setAttribute("value",$datos['fechaI']);                
            }
            $valores=array
            (
              "titulo"  => $this->tfor,
              "form"    => $form,
              'url'     => $this->getRequest()->getBaseUrl(),           
              "lin"     => $this->lin,
              "ttablas" => "Empleado, Fecha inicial, Fecha final, días pagados, Días Pendientes, Días solicitados",
              "datos"   => $d->getGeneral("select a.*,b.CedEmp, b.nombre, b.apellido, 
                           case when c.dias is null then 0 else c.dias end as dias from n_libvacaciones a inner join a_empleados b 
                           on b.id=a.idEmp 
                           left join n_vacaciones_p c on c.idPvac=a.id 
                           left join n_vacaciones d on d.id=c.idVac 
                           where a.estado=0 and a.idEmp=".$data->idEmp." order by a.fechaI "),
            );      
           $view = new ViewModel($valores);        
           $this->layout('layout/blancoB'); // Layout del login
           return $view;        
         }
      }        
   }  
   // Generar promedio a pagar vacaciones
   public function listacAction() 
   { 
      $form = new Formulario("form");             
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d=new AlbumTable($this->dbAdapter);
      if($this->getRequest()->isPost()) // Actualizar 
      {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $data = $this->request->getPost();
            $diasVac = $data->total; // Dias solicitados para vacaciones
            
            // Buscar fecha de ultima salida a vacaciones                        
            $fecha = date_create($data->fecsal);
            // Buscar centro de costo 
            $dat = $d->getGeneral1("select idCcos from a_empleados where id=".$data->idEmp); 
            
            $datSab = $d->getGeneral1("select count(id) as sabado from c_general_dh where dia = 1 and idCcos = ".$dat['idCcos']); // Dia habil sabado
            $datDom = $d->getGeneral1("select count(id) as domingo from c_general_dh where dia = 2 and idCcos = ".$dat['idCcos']); // Dia habil domingo
            $dias=0;      
            $sw=0;
            $swI=0;
            $diasH  = 0;
            $diasNh = 0;
            $diasCal = 0;
            while ($sw==0)
            { 
              if ($swI==1) // Sumar a partir del segundo dia
                 date_add($fecha, date_interval_create_from_date_string(' 1 days')); 
              $swI=1;                  
              $fecReg = date_format($fecha, 'Y-m-d');              
              
              if ( substr($fecReg,8,2)!=31) // Si 31 no se suma 
                   $diasCal = $diasCal + 1;  
              
              if ( (substr($fecReg,8,2)==28) and (substr($fecReg,5,2)==02) ) // Si febrero dos dia segun año
                   $diasCal = $diasCal + 2;  
              if ( (substr($fecReg,8,2)==29) and (substr($fecReg,5,2)==02) ) // Si febrero un dia segun año
                   $diasCal = $diasCal + 1;                
              
              //echo $fecReg.'-'.substr($fecReg,8,2).' : '.$diasCal.' <br />';         
              
              $diaSemana = $this->diaSemana(substr($fecReg,0,4), substr($fecReg,5,2) , substr($fecReg,8,2)); // Devuelve el dia de semana
              if ( substr($fecReg,8,2)!=31) // Si 31 no se suma 
              {
               
                 if ($diaSemana==0)// Domingo
                 {
                    if ($datDom['domingo']==1) // Si es un es un dia habil
                       { $diasH++;$dias++;}
                    else // Si no es un dia on habil
                       $diasNh++;
                 }
              
                 if ($diaSemana==6)// Sabado
                 {
                    if ($datSab['sabado']==1) // Si es un es un dia habil
                       { $diasH++;$dias++;}
                    else // Si no es un dia on habil
                       $diasNh++;
                 }
                 if ( ($diaSemana!=6)and($diaSemana!=0) ) // Dias normales
                 {
                    //echo $fecReg.'  <br /> ';
                    $daNh = $d->getConfHn($fecReg); // Verficar si no esta marcado como dia no habil
                    if ($daNh=='')
                    {
                       $diasH++;$dias++;
                       ///echo $fecReg.' - '.$diasH.'  '.$dias.'<br /> ';
                    }  
                    else
                       $diasNh++;
                  }             
                  if ($dias==$diasVac)// Cuando se cumplan los dias de vacaciones pedidos
                     $sw=1;
                }// Dia 31 no se cuenta 
              
            } // Fin recorrido dias de vacaciones 
            
            // Calcular el dia real de regeso

            date_add($fecha, date_interval_create_from_date_string(' 1 days')); 
            $fecRegR = date_format($fecha, 'Y-m-d');  
            //echo $fecReg;            
            
            // --
            $valores=array
            (
              "titulo"  => $this->tfor,
              "form"    => $form,
              'url'     => $this->getRequest()->getBaseUrl(),           
              "lin"     => $this->lin,
              "datos"   => $d->getVacaP($data->idEmp, $data->fecsal),
              "datEmp"  => $d->getEmp(" and id=".$data->idEmp),                
              "fecReg"  => $fecReg,      
              "fecRegR"  => $fecRegR,      
              "diasHab" => $dias,
              "diasNhab"=> $diasNh,
              "diasCal" => $diasCal,  
              "dias"    => $data->total,                  
            );      
           $view = new ViewModel($valores);        
           $this->layout('layout/blancoB'); // Layout del login
           return $view;        
         }
      }        
   }     
   // Generar vacaciones
   public function listgAction() 
   { 
      $form = new Formulario("form");             
      $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
      $d=new AlbumTable($this->dbAdapter);
      if($this->getRequest()->isPost()) // Actualizar 
      {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
            $u    = new Vacaciones($this->dbAdapter);// ------------------------------------------------- 3 FUNCION DENTRO DEL MODELO (C)  
            $data = $this->request->getPost();
            //print_r($data);
            $id = $u->actRegistro($data);
            if ($data->idVac>0)
               $id = $data->idVac; 
            
            // Periodos de vacaciones
            $u    = new VacacionesP($this->dbAdapter);
            $i=0;
            while ($i < count($data->idPer))
            {
                if ($data->diasP[$i]>0)
                    $u->actRegistro($data->idPer[$i], $data->diasP[$i], $id);                
                $i++;
            }            
            // Actualizar empleado 
            if ($data->estado==1)
               $d->modGeneral("update a_empleados set idVac=".$id." where id=".$data->idEmp); 
         }
      }   
      $view = new ViewModel();        
      $this->layout('layout/blanco'); // Layout del login
      return $view;       
   }
   
   
   function diaSemana($ano,$mes,$dia)
   {
	// 0->domingo	 | 6->sabado
	$dia= date("w",mktime(0, 0, 0, $mes, $dia, $ano));
	return $dia;
   }

    // Listado de registros ********************************************************************************************
    public function listdAction()
    {            
        $this->dbAdapter=$this->getServiceLocator()->get('Zend\Db\Adapter');
        $u=new AlbumTable($this->dbAdapter); // ---------------------------------------------------------- 1 FUNCION DENTRO DEL MODELO (C)      
        $id = (int) $this->params()->fromRoute('id', 0);
        $dat = $u->getGeneral1("select idEmp from n_vacaciones where id=".$id);
        // INICIO DE TRANSACCIONES
        $connection = null;
        try {
            $connection = $this->dbAdapter->getDriver()->getConnection();
   	    $connection->beginTransaction();                                                           
            
            $u->modGeneral("update a_empleados set idVac=0 where id=".$dat['idEmp']);
            $u->modGeneral("delete from n_vacaciones_p where idVac=".$id);
            $u->modGeneral("delete from n_vacaciones where id=".$id);
            $connection->commit();  
            return $this->redirect()->toUrl($this->getRequest()->getBaseUrl().$this->lin);            
            // FIN GUARDADO 
           }// Fin try casth   
           catch (\Exception $e) {
              if ($connection instanceof \Zend\Db\Adapter\Driver\ConnectionInterface) {
     	          $connection->rollback();
                   echo $e;
 	      }	
 	            /* Other error handling */
           }// FIN TRANSACCION                                                               
            
        
    } // Fin listar registros         
   
}
